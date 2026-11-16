import unittest
from readonly_inventory import inventory, PORTS

class InventoryTests(unittest.TestCase):
 def test_allowlisted_commands_no_false_go_and_all_three_ports(self):
  seen=[]
  def fake(args):
   seen.append(args)
   if args[:3]==['docker','ps','-a']:return 'imagine-synthetic-one\nimagine-synthetic-two\n'
   if args[:2]==['docker','network']:return 'net-one\n'
   if args[:2]==['docker','volume']:return 'vol-one\n'
   if args[:2]==['docker','version']:return '27.5.1\n'
   if args[:3]==['docker','compose','version']:return 'v2.32.4\n'
   if args[0]=='ss':
    return 'LISTEN 0 128 *:18081\n' if args[-1]=='sport = :18081' else ''
   raise AssertionError('unexpected non-readonly command '+str(args))
  report=inventory(fake)
  self.assertEqual('blocked',report['status'])
  self.assertEqual('unverified',report['host_identity'])
  self.assertEqual(0,report['changes'])
  self.assertEqual('N8N_HOST_INVENTORY_V2',report['schema'])
  self.assertEqual({'17990':'observed_free_not_reserved','18081':'occupied',
                    '18090':'observed_free_not_reserved'},report['ports'])
  self.assertEqual(report['ports']['17990'],report['port_17990'])
  self.assertEqual('27.5.1',report['docker']['server_version'])
  self.assertEqual('2.32.4',report['docker']['compose_version'])
  self.assertEqual(8,len(seen))
  self.assertEqual(3,sum(x[0]=='ss' for x in seen))
  self.assertNotIn('imagine-synthetic',str(report))
  for command in seen:
   self.assertNotIn(command[1] if len(command)>1 else '', ['inspect','logs','exec','run','up','rm'])
 def test_missing_tools_remain_blocked(self):
  def missing(args):raise OSError()
  report=inventory(missing)
  self.assertFalse(report['docker']['available'])
  self.assertEqual('blocked',report['status'])
  self.assertTrue(all(x=='unverified' for x in report['ports'].values()))
 def test_no_version_leak_if_unexpected_format(self):
  def fake(args):
   if args[:2]==['docker','version']:return 'docker@secret-host:user/pass\n'
   if args[:3]==['docker','compose','version']:return 'v2.34.0-with-credentials\n'
   if args[0]=='ss':return ''
   return 'a\nb\n'
  report=inventory(fake)
  self.assertIsNone(report['docker']['server_version'])
  self.assertIsNone(report['docker']['compose_version'])
  self.assertNotIn('secret-host',str(report))
 def test_ports_constant(self):
  self.assertEqual((17990,18081,18090),PORTS)

if __name__=='__main__':
 unittest.main()
