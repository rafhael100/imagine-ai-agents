import unittest
from readonly_inventory import inventory
class InventoryTests(unittest.TestCase):
 def test_allowlisted_readonly_commands_and_no_false_go(self):
  seen=[]
  def fake(args):
   seen.append(args)
   if args[0]=='ss':return ''
   return 'imagine-synthetic-one\nimagine-synthetic-two\n'
  r=inventory(fake)
  self.assertEqual('blocked',r['status']);self.assertEqual(0,r['changes'])
  self.assertEqual('observed_free_not_reserved',r['port_17990'])
  self.assertEqual(4,len(seen));self.assertNotIn('imagine-synthetic',str(r))
 def test_missing_tools_remain_blocked(self):
  def missing(args):raise OSError()
  r=inventory(missing);self.assertFalse(r['docker']['available']);self.assertEqual('blocked',r['status']);self.assertEqual('unverified',r['port_17990'])
if __name__=='__main__':unittest.main()
