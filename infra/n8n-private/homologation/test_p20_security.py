import hashlib,io,json,os,subprocess,tarfile,tempfile,unittest
from pathlib import Path
from material_preflight import validate,fingerprint,Denied
from seal_backup import seal,verify_decrypted,COMPONENTS

class P20Security(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  cls.work=tempfile.TemporaryDirectory();cls.root=Path(cls.work.name)
  def cmd(*args):return subprocess.run(['openssl',*map(str,args)],capture_output=True,check=True)
  cls.cmd=staticmethod(cmd)
  cmd('req','-x509','-newkey','rsa:2048','-nodes','-keyout',cls.root/'ca.key','-out',cls.root/'ca.crt','-days','3','-subj','/CN=SYNTHETIC_P20_CA')
  for name,usage,host in [('client','clientAuth','client-ci.internal'),('server','serverAuth','n8n-ci.internal'),('recipient','emailProtection','backup-ci.internal')]:
   cmd('req','-newkey','rsa:2048','-nodes','-keyout',cls.root/(name+'.key'),'-out',cls.root/(name+'.csr'),'-subj','/CN='+host)
   ext=cls.root/(name+'.ext');ext.write_text('extendedKeyUsage='+usage+'\nsubjectAltName=DNS:'+host+'\n')
   cmd('x509','-req','-in',cls.root/(name+'.csr'),'-CA',cls.root/'ca.crt','-CAkey',cls.root/'ca.key','-CAcreateserial','-out',cls.root/(name+'.crt'),'-days','2','-extfile',ext)
  for p in cls.root.iterdir():p.chmod(0o600)
 @classmethod
 def tearDownClass(cls):cls.work.cleanup()
 def material(self,**changes):
  args=dict(ca=self.root/'ca.crt',cert=self.root/'client.crt',key=self.root/'client.key',expected=fingerprint(self.root/'client.crt'))
  args.update(changes);return validate(**args)
 def snapshot(self):
  d=Path(tempfile.mkdtemp(dir=self.root));d.chmod(0o700)
  for name in COMPONENTS:
   p=d/name;p.write_bytes(b'PGDMP_SYNTHETIC_FORMAT_FIXTURE' if name.endswith('.dump') else b'k'*64);p.chmod(0o600)
  with tarfile.open(d/'n8n-data.tar','w') as t:
   v=b'SYNTHETIC_VOLUME_CONFIG';i=tarfile.TarInfo('config');i.size=len(v);t.addfile(i,io.BytesIO(v))
  return d
 def bundle(self):
  src=self.snapshot();target=src.parent/(src.name+'.cms')
  result=seal(src,self.root/'recipient.crt',fingerprint(self.root/'recipient.crt'),target)
  return src,target,result
 def decrypt(self,bundle,target,key='recipient.key'):
  return subprocess.run(['openssl','cms','-decrypt','-binary','-inform','DER','-in',str(bundle),'-recip',str(self.root/'recipient.crt'),'-inkey',str(self.root/key),'-out',str(target)],capture_output=True)
 def test_material_valid_does_not_grant_installation(self):
  v=self.material();self.assertTrue(v['material_valid']);self.assertFalse(v['go_for_installation']);self.assertFalse(v['grants_or_revocation_verified'])
 def test_material_rejects_wrong_pin(self):
  with self.assertRaises(Denied):self.material(expected='0'*64)
 def test_material_rejects_wrong_private_key(self):
  with self.assertRaises(Denied):self.material(key=self.root/'server.key')
 def test_material_rejects_world_readable_key(self):
  p=self.root/'client.key';p.chmod(0o644)
  try:
   with self.assertRaises(Denied):self.material()
  finally:p.chmod(0o600)
 def test_material_rejects_symlink(self):
  p=self.root/'symlink.key';p.symlink_to(self.root/'client.key')
  try:
   with self.assertRaises(Denied):self.material(key=p)
  finally:p.unlink()
 def test_material_requires_server_hostname_and_purpose(self):
  with self.assertRaises(Denied):self.material(purpose='sslserver',hostname='n8n-ci.internal')
  self.assertTrue(self.material(cert=self.root/'server.crt',key=self.root/'server.key',expected=fingerprint(self.root/'server.crt'),purpose='sslserver',hostname='n8n-ci.internal')['material_valid'])
  with self.assertRaises(Denied):self.material(cert=self.root/'server.crt',key=self.root/'server.key',expected=fingerprint(self.root/'server.crt'),purpose='sslserver',hostname='other-ci.internal')
 def test_material_rejects_insufficient_lifetime(self):
  with self.assertRaises(Denied):self.material(minimum=259200)
 def test_crypto_roundtrip_and_manifest_do_not_attest_restore(self):
  src,out,result=self.bundle();plain=src/'quarantine.tar';self.assertEqual(0,self.decrypt(out,plain).returncode)
  self.assertTrue(verify_decrypted(plain)['component_hashes_match']);self.assertFalse(result['external_backup_verified']);self.assertFalse(result['go_for_installation']);self.assertEqual(0o600,out.stat().st_mode&0o777)
 def test_crypto_rejects_ciphertext_corruption(self):
  src,out,_=self.bundle();b=bytearray(out.read_bytes());b[-20]^=1;out.write_bytes(b)
  self.assertNotEqual(0,self.decrypt(out,src/'untrusted-quarantine.tar').returncode)
 def test_crypto_rejects_wrong_recipient_key(self):
  src,out,_=self.bundle();self.assertNotEqual(0,self.decrypt(out,src/'wrong-quarantine.tar','server.key').returncode)
 def test_seal_never_overwrites_or_uses_wrong_recipient(self):
  src,out,_=self.bundle()
  with self.assertRaises(FileExistsError):seal(src,self.root/'recipient.crt',fingerprint(self.root/'recipient.crt'),out)
  with self.assertRaises(Denied):seal(src,self.root/'recipient.crt','0'*64,src/'wrong.cms')
 def test_seal_rejects_missing_service_snapshot(self):
  src=self.snapshot();(src/'agents-service.dump').unlink()
  with self.assertRaises(OSError):seal(src,self.root/'recipient.crt',fingerprint(self.root/'recipient.crt'),src/'missing.cms')
 def test_seal_rejects_plain_sql_dump_and_insecure_key(self):
  src=self.snapshot();(src/'crm-service.dump').write_bytes(b'CREATE TABLE fake;')
  with self.assertRaises(Denied):seal(src,self.root/'recipient.crt',fingerprint(self.root/'recipient.crt'),src/'bad.cms')
  src=self.snapshot();(src/'n8n-encryption.key').chmod(0o644)
  with self.assertRaises(Denied):seal(src,self.root/'recipient.crt',fingerprint(self.root/'recipient.crt'),src/'bad.cms')
 def test_seal_rejects_volume_traversal(self):
  src=self.snapshot()
  with tarfile.open(src/'n8n-data.tar','w') as t:
   i=tarfile.TarInfo('../outside');i.size=1;t.addfile(i,io.BytesIO(b'x'))
  with self.assertRaises(Denied):seal(src,self.root/'recipient.crt',fingerprint(self.root/'recipient.crt'),src/'bad.cms')
 def test_verification_rejects_modified_plaintext(self):
  src,out,_=self.bundle();plain=src/'quarantine.tar';self.assertEqual(0,self.decrypt(out,plain).returncode)
  with tarfile.open(plain) as t:parts=[(x.name,t.extractfile(x).read()) for x in t]
  changed=src/'changed.tar'
  with tarfile.open(changed,'w') as t:
   for name,data in parts:
    if name=='crm-service.dump':data+=b'ALTERED'
    i=tarfile.TarInfo(name);i.size=len(data);t.addfile(i,io.BytesIO(data))
  with self.assertRaises(Denied):verify_decrypted(changed)

 def test_verifier_rejects_missing_manifest_without_extracting(self):
  d=self.snapshot();empty=d/'empty.tar'
  with tarfile.open(empty,'w'):pass
  with self.assertRaises(Denied):verify_decrypted(empty)
 def test_seal_rejects_unsafe_output_directory(self):
  src=self.snapshot();outdir=self.root/'unsafe';outdir.mkdir();outdir.chmod(0o777)
  try:
   with self.assertRaises(Denied):seal(src,self.root/'recipient.crt',fingerprint(self.root/'recipient.crt'),outdir/'bad.cms')
  finally:outdir.chmod(0o700)

if __name__=='__main__':unittest.main()
