import copy
import json
import unittest
from readonly_inventory import inventory
from assess_readonly_host import analyze,InvalidInventory

class AssessmentTests(unittest.TestCase):
 def sample(self):
  def fake(args):
   if args[0]=='ss':return 'LISTEN\n' if args[-1]=='sport = :18081' else ''
   if args[:2]==['docker','version']:return '27.5.1'
   if args[:3]==['docker','compose','version']:return 'v2.32.4'
   return 'synthetic-ci-name\n'
  return inventory(fake)
 def test_normal_observation_is_always_no_go(self):
  report=analyze(self.sample())
  self.assertEqual('NO_GO',report['result'])
  self.assertFalse(report['production_authorization'])
  self.assertFalse(report['host_identity_verified'])
  self.assertEqual('UNAUTHENTICATED_SNAPSHOT',report['source_claim'])
  self.assertEqual('occupied',report['port_observation']['18081'])
  self.assertNotIn('synthetic-ci-name',json.dumps(report))
 def test_missing_or_low_capacity_never_grants(self):
  data=self.sample()
  data['memory_available_bytes']=None
  data['cpu_count']=2
  data['disk_free_bytes']=0
  report=analyze(data)
  self.assertEqual('insufficient_or_unknown',report['provisional_capacity'])
  self.assertIn('RAM_HEADROOM_NOT_PROVEN',report['risk_flags'])
  self.assertIn('CPU_HEADROOM_NOT_PROVEN',report['risk_flags'])
  self.assertIn('DISK_HEADROOM_NOT_PROVEN',report['risk_flags'])
 def test_mutations_rejected(self):
  base=self.sample()
  mutations=[
   ('approved_flag',lambda d:d.update(status='approved')),
   ('spoof_host',lambda d:d.update(host_identity='Leaseweb')),
   ('changes',lambda d:d.update(changes=1)),
   ('secret',lambda d:d.update(ssh_private_key='LEAK')),
   ('port_added',lambda d:d['ports'].update({'30000':'occupied'})),
   ('port_lost',lambda d:d['ports'].pop('18081')),
   ('port_state',lambda d:d['ports'].update({'18090':'safe'})),
   ('port_mismatch',lambda d:d.update(port_17990='occupied')),
   ('wrong_schema',lambda d:d.update(schema='N8N_HOST_INVENTORY_V1')),
   ('bad_capacity',lambda d:d.update(memory_available_bytes=-1)),
   ('bool_capacity',lambda d:d.update(cpu_count=True)),
   ('docker_version_secret',lambda d:d['docker'].update(server_version='https://example.test/token')),
   ('docker_env_leak',lambda d:d['docker'].update(environ=['password'])),
   ('docker_container_raw',lambda d:d['docker'].update(container_refs=['imagine-site-container'])),
   ('docker_negative',lambda d:d['docker'].update(containers=-1)),
   ('gates_secret',lambda d:d['gates'].append('MY PRIVATE PASSWORD')),
  ]
  for label,mutate in mutations:
   d=copy.deepcopy(base)
   mutate(d)
   with self.subTest(label=label),self.assertRaises(InvalidInventory):
    analyze(d)
 def test_unavailable_docker_keeps_no_go(self):
  data=self.sample()
  data['docker']={'available':False,'server_version':None,'compose_version':None}
  r=analyze(data)
  self.assertEqual('NO_GO',r['result'])
  self.assertIn('DOCKER_UNVERIFIED',r['risk_flags'])

if __name__=='__main__':
 unittest.main()
