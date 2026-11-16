#!/usr/bin/env python3
"""Offline material checks only. Never grants access, changes files or installs services."""
import argparse,hashlib,json,re,stat,subprocess
from pathlib import Path

class Denied(ValueError):pass

def regular(path,private=False):
 p=Path(path);s=p.lstat()
 if not stat.S_ISREG(s.st_mode) or s.st_nlink!=1:raise Denied('REGULAR_SINGLE_LINK_REQUIRED')
 if (private and stat.S_IMODE(s.st_mode) not in (0o400,0o600)) or (not private and s.st_mode&0o022):raise Denied('UNSAFE_FILE_PERMISSIONS')
 if not 0<s.st_size<65536:raise Denied('MATERIAL_SIZE_DENIED')
 return p

def openssl(*args):
 p=subprocess.run(['openssl',*map(str,args)],capture_output=True,timeout=10,check=False)
 if p.returncode:raise Denied('OPENSSL_VALIDATION_FAILED')
 return p.stdout

def fingerprint(cert):
 return hashlib.sha256(openssl('x509','-in',cert,'-outform','DER')).hexdigest()

def validate(ca,cert,key,expected,purpose='sslclient',minimum=86400,hostname=None):
 ca=regular(ca);cert=regular(cert);key=regular(key,True)
 if not re.fullmatch('[a-f0-9]{64}',expected):raise Denied('PIN_REQUIRED')
 if fingerprint(cert)!=expected:raise Denied('CERTIFICATE_PIN_MISMATCH')
 if purpose not in ['sslclient','sslserver'] or not 60<=minimum<=2592000:raise Denied('POLICY_INVALID')
 openssl('verify','-CAfile',ca,'-purpose',purpose,cert)
 openssl('x509','-in',cert,'-noout','-checkend',str(minimum))
 if purpose=='sslserver':
  if not hostname or not re.fullmatch('[a-z0-9.-]{1,253}',hostname):raise Denied('SERVER_HOSTNAME_REQUIRED')
  openssl('verify','-CAfile',ca,'-purpose',purpose,'-verify_hostname',hostname,cert)
 public_cert=openssl('x509','-in',cert,'-pubkey','-noout')
 public_key=openssl('pkey','-in',key,'-pubout')
 if public_cert!=public_key:raise Denied('KEY_CERTIFICATE_MISMATCH')
 return {'schema':'IMAGINE_MTLS_MATERIAL_CHECK_V1','material_valid':True,'certificate_validity_checked':True,
         'private_permissions_checked':True,'purpose_checked':True,'grants_or_revocation_verified':False,
         'custody_or_operator_approval_verified':False,'go_for_installation':False}

if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--ca',required=True);p.add_argument('--certificate',required=True);p.add_argument('--private-key',required=True);p.add_argument('--expected-fingerprint',required=True);p.add_argument('--purpose',choices=['sslclient','sslserver'],default='sslclient');p.add_argument('--hostname');p.add_argument('--minimum-seconds',type=int,default=86400)
 a=p.parse_args()
 try:print(json.dumps(validate(a.ca,a.certificate,a.private_key,a.expected_fingerprint,a.purpose,a.minimum_seconds,a.hostname),sort_keys=True))
 except (Denied,OSError,subprocess.SubprocessError):
  print(json.dumps({'schema':'IMAGINE_MTLS_MATERIAL_CHECK_V1','material_valid':False,'reason':'MATERIAL_DENIED','go_for_installation':False}));raise SystemExit(2)
