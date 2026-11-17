#!/usr/bin/env python3
"""CENTRAL-AUTO-P23 — synthetic Ed25519 backup provenance receipts.

Encryption is not authorship. Require an independent trusted signing public
key fingerprint and verified signature before accepting a CMS backup copy.
No custody, external transfer or operational source identity is provided.
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import stat
import subprocess
import time
from pathlib import Path

SCHEMA="CENTRAL_AUTO_P23_SIGNED_BACKUP_V1"
SOURCE="SYNTHETIC_GITHUB_ACTIONS_BACKUP_ONLY"
ISSUER="SYNTHETIC_BACKUP_CUSTODIAN_CI"
AUDIENCE="IMAGINE_N8N_RECOVERY_REVIEW_CI"
KEYS={
 "schema","source","issuer","audience","package_sha256","package_bytes",
 "signing_key_fingerprint","issued_at","expires_at","nonce_ref",
 "external_copy_verified","real_custodian_identity_verified",
 "recovery_in_different_host_verified","go_for_installation"
}
HEX64=re.compile(r"^[a-f0-9]{64}$")
NONCE=re.compile(r"^SYNTHETIC_RECEIPT_[A-F0-9]{32}$")
FALSE_FLAGS=("external_copy_verified","real_custodian_identity_verified",
             "recovery_in_different_host_verified","go_for_installation")
# RFC 8410 SubjectPublicKeyInfo: id-Ed25519, absent parameters, 32 key bytes.
ED25519_SPKI_PREFIX=bytes.fromhex("302a300506032b6570032100")

class Rejected(Exception):
    pass

def private_file(path:Path)->Path:
    try:
        s=path.lstat()
    except OSError as exc:
        raise Rejected("MISSING_PRIVATE_FILE") from exc
    if not (stat.S_ISREG(s.st_mode) and s.st_nlink==1 and
            stat.S_IMODE(s.st_mode) in (0o400,0o600) and s.st_size>0):
        raise Rejected("PRIVATE_FILE_PERMISSIONS")
    return path

def sha256_file(path:Path)->tuple[str,int]:
    if path.is_symlink() or not path.is_file():
        raise Rejected("CIPHERTEXT_NOT_REGULAR")
    size=path.stat().st_size
    if not 10<size<20*1024**3:
        raise Rejected("CIPHERTEXT_SIZE_INVALID")
    dig=hashlib.sha256()
    with path.open("rb") as file:
        for block in iter(lambda:file.read(1024*1024),b""):
            dig.update(block)
    return dig.hexdigest(),size

def call_openssl(args:list[str],input_data:bytes|None=None)->bytes:
    try:
        r=subprocess.run(["openssl",*args],input=input_data,
                         stdout=subprocess.PIPE,stderr=subprocess.PIPE,
                         check=False,timeout=12)
    except (OSError,subprocess.TimeoutExpired) as exc:
        raise Rejected("OPENSSL_UNAVAILABLE") from exc
    if r.returncode:
        raise Rejected("SIGNATURE_DENIED")
    return r.stdout

def public_fingerprint(pub:Path)->str:
    if pub.is_symlink() or not pub.is_file() or pub.stat().st_size>16384:
        raise Rejected("PUBLIC_SIGNER_KEY_INVALID")
    der=call_openssl(["pkey","-pubin","-in",str(pub),"-outform","DER"])
    if len(der)!=44 or not der.startswith(ED25519_SPKI_PREFIX):
        raise Rejected("ED25519_SIGNER_REQUIRED")
    return hashlib.sha256(der).hexdigest()

def canonical(receipt:dict)->bytes:
    return json.dumps(receipt,sort_keys=True,separators=(",",":"),
                      ensure_ascii=True,allow_nan=False).encode("ascii")

def remove_created(path:Path,identity:tuple[int,int])->None:
    """Clean up only our exclusive-created inode, never a competing output.

    Output parents must still be trusted directories without hostile writers;
    this is not a filesystem sandbox against a same-user adversary.
    """
    try:
        current=path.lstat()
        if (current.st_dev,current.st_ino)==identity:
            path.unlink()
    except FileNotFoundError:
        pass

def safe_write(path:Path,payload:bytes)->tuple[int,int]:
    parent=path.parent.lstat()
    if not stat.S_ISDIR(parent.st_mode) or parent.st_mode&0o022:
        raise Rejected("UNSAFE_OUTPUT_DIRECTORY")
    try:
        fd=os.open(path,os.O_CREAT|os.O_EXCL|os.O_WRONLY|os.O_NOFOLLOW,0o600)
    except FileExistsError as exc:
        raise Rejected("NO_OVERWRITE") from exc
    state=os.fstat(fd)
    identity=(state.st_dev,state.st_ino)
    try:
        with os.fdopen(fd,"wb") as out:
            out.write(payload);out.flush();os.fsync(out.fileno())
    except BaseException:
        remove_created(path,identity)
        raise
    return identity

def validate_receipt(receipt:object,now:int)->dict:
    if not isinstance(receipt,dict) or set(receipt)!=KEYS:
        raise Rejected("CLOSED_RECEIPT_KEYS")
    if any(receipt.get(k)!=v for k,v in {
        "schema":SCHEMA,"source":SOURCE,"issuer":ISSUER,"audience":AUDIENCE,
    }.items()) or any(receipt[k] is not False for k in FALSE_FLAGS):
        raise Rejected("SYNTHETIC_SCOPE_REQUIRED")
    for key in ("package_sha256","signing_key_fingerprint"):
        if not isinstance(receipt[key],str) or not HEX64.fullmatch(receipt[key]):
            raise Rejected("HASH_FORMAT")
    if (type(receipt["package_bytes"]) is not int or
        not 10<receipt["package_bytes"]<20*1024**3):
        raise Rejected("CONTENT_SIZE")
    if not isinstance(receipt["nonce_ref"],str) or (
        NONCE.fullmatch(receipt["nonce_ref"]) is None
    ):
        raise Rejected("NONCE_FORMAT")
    issued=receipt["issued_at"];expires=receipt["expires_at"]
    if (type(issued) is not int or type(expires) is not int
        or type(now) is not int or not issued<expires
        or expires-issued>3600 or issued>now+5 or expires<=now
        or now-issued>3600):
        raise Rejected("RECEIPT_EXPIRED_OR_UNBOUNDED")
    return receipt

def sign(ciphertext:Path,priv:Path,pub:Path,metadata:Path,signature:Path,
         *,now:int,nonce:str)->dict:
    private_file(priv)
    if os.path.lexists(metadata) or os.path.lexists(signature):
        raise Rejected("NO_OVERWRITE")
    signing_fingerprint=public_fingerprint(pub)
    private_public=call_openssl(["pkey","-in",str(priv),"-pubout",
                                "-outform","DER"])
    if hashlib.sha256(private_public).hexdigest()!=signing_fingerprint:
        raise Rejected("SIGNING_KEYPAIR_MISMATCH")
    digest,length=sha256_file(ciphertext)
    receipt={
      "schema":SCHEMA,"source":SOURCE,"issuer":ISSUER,"audience":AUDIENCE,
      "package_sha256":digest,"package_bytes":length,
      "signing_key_fingerprint":signing_fingerprint,
      "issued_at":now,"expires_at":now+1200,"nonce_ref":nonce,
      "external_copy_verified":False,
      "real_custodian_identity_verified":False,
      "recovery_in_different_host_verified":False,
      "go_for_installation":False,
    }
    validate_receipt(receipt,now)
    original=canonical(receipt)
    # Ed25519 pkeyutl needs a seekable input with known size. Do not
    # sign via stdin; sign the exact protected canonical metadata file.
    created=[]
    try:
        created.append((metadata,safe_write(metadata,original)))
        signed=call_openssl(["pkeyutl","-sign","-rawin","-inkey",
                             str(priv),"-in",str(metadata)])
        if len(signed)!=64:
            raise Rejected("NOT_ED25519_SIGNATURE")
        created.append((signature,safe_write(signature,signed)))
    except BaseException:
        for path,identity in reversed(created):
            remove_created(path,identity)
        raise
    return receipt

def verify(ciphertext:Path,pub:Path,metadata:Path,signature:Path,
           *,pinned_fingerprint:str,now:int)->dict:
    if not isinstance(pinned_fingerprint,str) or not HEX64.fullmatch(pinned_fingerprint):
        raise Rejected("PIN_REQUIRED")
    actual_fingerprint=public_fingerprint(pub)
    if actual_fingerprint!=pinned_fingerprint:
        raise Rejected("UNTRUSTED_SIGNER")
    private_file(metadata);private_file(signature)
    if metadata.stat().st_size>2048 or signature.stat().st_size!=64:
        raise Rejected("SIGNED_RECEIPT_SIZE")
    raw=metadata.read_bytes()
    def unique(pairs):
        d={}
        for k,v in pairs:
            if k in d:
                raise Rejected("DUPLICATE_JSON_KEY")
            d[k]=v
        return d
    try:
        receipt=json.loads(raw,object_pairs_hook=unique)
    except (ValueError,UnicodeError) as exc:
        raise Rejected("INVALID_RECEIPT_JSON") from exc
    validate_receipt(receipt,now)
    if raw!=canonical(receipt):
        raise Rejected("NONCANONICAL_SIGNED_RECEIPT")
    if receipt["signing_key_fingerprint"]!=pinned_fingerprint:
        raise Rejected("RECEIPT_KEY_MISMATCH")
    digest,length=sha256_file(ciphertext)
    if (receipt["package_sha256"]!=digest or
        receipt["package_bytes"]!=length):
        raise Rejected("CIPHERTEXT_CHANGED")
    call_openssl(["pkeyutl","-verify","-rawin","-pubin","-inkey",
                  str(pub),"-sigfile",str(signature),"-in",str(metadata)])
    return {
        "schema":"CENTRAL_AUTO_P23_VERIFICATION_V1",
        "cms_package_hash_matches":True,
        "ed25519_signature_valid":True,
        "trusted_public_key_pin_matches":True,
        "signed_scope":"SYNTHETIC_CI_ONLY",
        "issuer_credential_custody_verified":False,
        "external_copy_verified":False,
        "go_for_installation":False,
    }
