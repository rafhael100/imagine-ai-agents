#!/usr/bin/env python3
"""Minimal, read-only metadata. No env, inspect, logs, mounts, private keys or content reads."""
import argparse,hashlib,json,os,shutil,subprocess

def run(args):
 p=subprocess.run(args,capture_output=True,text=True,timeout=10)
 if p.returncode:raise RuntimeError('inventory command unavailable')
 return p.stdout

def inventory(execute=run):
 report={'schema':'N8N_HOST_INVENTORY_V1','status':'blocked','host_identity':'unverified','changes':0,
  'cpu_count':os.cpu_count(),'disk_free_bytes':shutil.disk_usage('/').free,
  'docker':{'available':False},'port_17990':'unverified',
  'gates':['authorized_host_identity','licensing','secrets','tls_service_identity','backup_external_restore','private_access','operational_release']}
 try:
  names=execute(['docker','ps','-a','--format','{{.Names}}']).splitlines()
  networks=execute(['docker','network','ls','--format','{{.Name}}']).splitlines()
  volumes=execute(['docker','volume','ls','--format','{{.Name}}']).splitlines()
  report['docker']={'available':True,'containers':len(names),'networks':len(networks),'volumes':len(volumes),
   'container_refs':[hashlib.sha256(x.encode()).hexdigest()[:16] for x in names]}
 except (OSError,RuntimeError,subprocess.TimeoutExpired):pass
 try:
  listeners=execute(['ss','-H','-ltn','sport = :17990']).strip()
  report['port_17990']='occupied' if listeners else 'observed_free_not_reserved'
 except (OSError,RuntimeError,subprocess.TimeoutExpired):pass
 return report
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('--probe-host',action='store_true',required=True);p.parse_args()
 print(json.dumps(inventory(),sort_keys=True))
