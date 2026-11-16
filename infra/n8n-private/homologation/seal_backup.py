#!/usr/bin/env python3
"""Seal an operator-prepared snapshot, not a backup collector/uploader or deployment tool."""
import argparse,hashlib,io,json,os,re,stat,subprocess,tarfile
from pathlib import Path,PurePosixPath
from material_preflight import Denied,regular,fingerprint,openssl

COMPONENTS=('n8n-pg.dump','n8n-data.tar','n8n-encryption.key','crm-service.dump','agents-service.dump')
LIMIT=20*1024**3

def digest(path):
 h=hashlib.sha256()
 with open(path,'rb') as f:
  for block in iter(lambda:f.read(1024*1024),b''):h.update(block)
 return h.hexdigest()

def inspect_volume(source):
 """Reject malicious nested volume entries before sealing AND after decrypting.

 Accept a filesystem path or a non-seekable outer-TAR member stream.
 No extraction and no buffering of the 20 GiB maximum volume.
 """
 total=0;count=0;seen={};parents=set()
 try:
  if hasattr(source,'read'):
   opened=tarfile.open(fileobj=source,mode='r|*')
  else:
   opened=tarfile.open(source,mode='r|*')
  with opened as archive:
   for entry in archive:
    count+=1
    if count>10000 or entry.size<0 or entry.size>LIMIT:
     raise Denied('UNSAFE_VOLUME_ARCHIVE')
    total+=entry.size
    if total>LIMIT or entry.type not in (tarfile.REGTYPE,tarfile.AREGTYPE,tarfile.DIRTYPE):
     raise Denied('UNSAFE_VOLUME_ARCHIVE')
    if entry.mode&0o6000:
     raise Denied('UNSAFE_VOLUME_PERMISSIONS')
    raw=entry.name
    if (not isinstance(raw,str) or len(raw)>240 or
        any(ord(ch)<32 for ch in raw) or '\\' in raw or ':' in raw):
     raise Denied('UNSAFE_VOLUME_PATH')
    if raw.startswith('./'):
     raw=raw[2:]
    if entry.isdir():
     raw=raw.rstrip('/')
    if raw in ('','.'):
     if not entry.isdir() or raw in seen:
      raise Denied('UNSAFE_VOLUME_ROOT')
     seen[raw]='directory'
     continue
    normalized=PurePosixPath(raw)
    if (normalized.is_absolute() or '..' in normalized.parts or
        normalized.as_posix()!=raw or raw in seen):
     raise Denied('UNSAFE_VOLUME_PATH')
    kind='file' if entry.isfile() else 'directory'
    if kind=='file' and raw in parents:
     raise Denied('VOLUME_FILE_OVERLAPS_DIRECTORY')
    parts=normalized.parts
    for i in range(1,len(parts)):
     p='/'.join(parts[:i])
     if seen.get(p)=='file':
      raise Denied('VOLUME_PARENT_IS_FILE')
     parents.add(p)
    seen[raw]=kind
    if kind=='file':
     stream=archive.extractfile(entry)
     if stream is None:
      raise Denied('UNREADABLE_VOLUME_MEMBER')
     # Fully read to detect truncated TAR content, but never extract.
     with stream:
      while stream.read(1024*1024):
       pass
 except (tarfile.TarError,EOFError,OSError,ValueError) as error:
  raise Denied('UNSAFE_VOLUME_ARCHIVE') from error

def seal(source,recipient,pin,output):
 source=Path(source);s=source.lstat()
 if not stat.S_ISDIR(s.st_mode) or s.st_mode&0o077 or s.st_mode&0o500!=0o500:raise Denied('PRIVATE_SNAPSHOT_DIRECTORY_REQUIRED')
 recipient=regular(recipient)
 if fingerprint(recipient)!=pin:raise Denied('BACKUP_RECIPIENT_PIN_MISMATCH')
 openssl('x509','-in',recipient,'-noout','-checkend','86400')
 files={};states={};total=0
 for name in COMPONENTS:
  path=source/name;meta=path.lstat()
  if not stat.S_ISREG(meta.st_mode) or meta.st_nlink!=1 or stat.S_IMODE(meta.st_mode) not in (0o400,0o600) or meta.st_size<=0:raise Denied('SNAPSHOT_COMPONENT_DENIED')
  total+=meta.st_size
  if total>LIMIT:raise Denied('SNAPSHOT_TOO_LARGE')
  if name.endswith('.dump'):
   with path.open('rb') as f:
    if f.read(5)!=b'PGDMP':raise Denied('CUSTOM_PG_DUMP_REQUIRED')
  if name=='n8n-encryption.key' and not 32<=meta.st_size<=256:raise Denied('ENCRYPTION_KEY_SIZE_DENIED')
  files[name]={'sha256':digest(path),'bytes':meta.st_size};states[name]=(meta.st_ino,meta.st_size,meta.st_mtime_ns)
 inspect_volume(source/'n8n-data.tar')
 manifest={'schema':'IMAGINE_RECOVERY_PACKAGE_V1','components':files,'recipient_fingerprint':pin,
           'coherent_source_snapshot_verified':False,'external_copy_verified':False,'application_restore_verified':False}
 target=Path(output)
 parent=target.parent.lstat()
 if not stat.S_ISDIR(parent.st_mode) or parent.st_mode&0o022:raise Denied('UNSAFE_OUTPUT_DIRECTORY')
 fd=os.open(target,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600)
 process=None
 try:
  with os.fdopen(fd,'wb') as ciphertext:
   process=subprocess.Popen(['openssl','cms','-encrypt','-aes-256-gcm','-binary','-stream','-outform','DER',str(recipient)],stdin=subprocess.PIPE,stdout=ciphertext,stderr=subprocess.PIPE)
   with tarfile.open(fileobj=process.stdin,mode='w|') as archive:
    for name in COMPONENTS:
     path=source/name;archive.add(path,arcname=name,recursive=False)
     meta=path.lstat()
     if (meta.st_ino,meta.st_size,meta.st_mtime_ns)!=states[name]:raise Denied('SNAPSHOT_CHANGED')
    data=json.dumps(manifest,sort_keys=True).encode();entry=tarfile.TarInfo('manifest.json');entry.size=len(data);entry.mode=0o600;archive.addfile(entry,io.BytesIO(data))
   process.stdin.close();process.stdin=None
   _,error=process.communicate(timeout=60)
   if process.returncode:raise Denied('CMS_ENCRYPTION_FAILED')
   ciphertext.flush();os.fsync(ciphertext.fileno())
  return {'schema':'IMAGINE_RECOVERY_SEAL_V1','ciphertext_sha256':digest(target),'components':len(COMPONENTS),
          'encrypted':True,'external_backup_verified':False,'restoration_verified':False,'go_for_installation':False}
 except BaseException:
  if process and process.poll() is None:process.kill();process.wait()
  target.unlink(missing_ok=True)
  raise

def verify_decrypted(path):
 # Quarantine only: call AFTER CMS decrypt exits 0, before extracting/restoring anything.
 expected=set(COMPONENTS)|{'manifest.json'};seen=set();payloads={};total=0;manifest=None
 with tarfile.open(path,'r:') as archive:
  for member in archive:
   total+=member.size
   if total>LIMIT:raise Denied('PACKAGE_TOO_LARGE')
   if member.name not in expected or member.name in seen or not member.isfile() or member.size>LIMIT:raise Denied('PACKAGE_SCHEMA_DENIED')
   seen.add(member.name);stream=archive.extractfile(member)
   if member.name=='manifest.json':
    if member.size>8192:raise Denied('MANIFEST_TOO_LARGE')
    manifest=json.load(stream)
   else:
    h=hashlib.sha256();size=0
    for block in iter(lambda:stream.read(1024*1024),b''):h.update(block);size+=len(block)
    payloads[member.name]={'sha256':h.hexdigest(),'bytes':size}
 if not isinstance(manifest,dict) or set(manifest)!={'schema','components','recipient_fingerprint','coherent_source_snapshot_verified','external_copy_verified','application_restore_verified'} or any(manifest[k] is not False for k in ['coherent_source_snapshot_verified','external_copy_verified','application_restore_verified']):raise Denied('MANIFEST_SCHEMA_DENIED')
 if seen!=expected or not isinstance(manifest.get('recipient_fingerprint'),str) or not re.fullmatch('[a-f0-9]{64}',manifest['recipient_fingerprint']) or manifest.get('schema')!='IMAGINE_RECOVERY_PACKAGE_V1' or manifest.get('components')!=payloads:raise Denied('PACKAGE_INTEGRITY_DENIED')
 # Even a valid component SHA may describe a *malicious* inner TAR.
 # Re-inspect the actual decrypted volume bytes before anyone extracts them.
 with tarfile.open(path,'r:') as outer:
  volume=outer.getmember('n8n-data.tar')
  stream=outer.extractfile(volume)
  if stream is None:raise Denied('VOLUME_MEMBER_MISSING')
  with stream:
   inspect_volume(stream)
 return {'schema':'IMAGINE_RECOVERY_INTEGRITY_V1','component_hashes_match':True,'volume_tar_safe_for_reviewed_extraction':True,'database_restore_verified':False,'go_for_installation':False}

if __name__=='__main__':
 p=argparse.ArgumentParser();sub=p.add_subparsers(dest='operation',required=True)
 s=sub.add_parser('seal');s.add_argument('--snapshot',required=True);s.add_argument('--recipient-certificate',required=True);s.add_argument('--expected-fingerprint',required=True);s.add_argument('--output',required=True)
 v=sub.add_parser('verify-decrypted');v.add_argument('--quarantine-tar',required=True)
 a=p.parse_args()
 try:
  result=seal(a.snapshot,a.recipient_certificate,a.expected_fingerprint,a.output) if a.operation=='seal' else verify_decrypted(a.quarantine_tar)
  print(json.dumps(result,sort_keys=True))
 except (Denied,OSError,ValueError,TypeError,AttributeError,tarfile.TarError,subprocess.SubprocessError):
  print(json.dumps({'schema':'IMAGINE_RECOVERY_PACKAGE_DENIED_V1','reason':'PACKAGE_DENIED','go_for_installation':False}));raise SystemExit(2)
