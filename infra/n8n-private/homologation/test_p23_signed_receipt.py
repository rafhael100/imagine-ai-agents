"""P23: independent signer pin, Ed25519 integrity, expiry and closed receipts."""
import hashlib
import json
import os
import subprocess
import tempfile
import unittest
from pathlib import Path

from signed_receipt_p23 import Rejected,public_fingerprint,sign,verify

def signer(directory:Path,name:str):
    private=directory/(name+".key")
    public=directory/(name+".pub")
    proc=subprocess.run(["openssl","genpkey","-algorithm","Ed25519",
                         "-out",str(private)],capture_output=True)
    assert proc.returncode==0
    private.chmod(0o600)
    proc=subprocess.run(["openssl","pkey","-in",str(private),"-pubout",
                         "-out",str(public)],capture_output=True)
    assert proc.returncode==0
    return private,public

class SignedReceiptP23(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root=Path(self.tmp.name)
        self.private,self.public=signer(self.root,"trusted")
        self.other_priv,self.other_pub=signer(self.root,"unknown")
        self.cipher=self.root/"synthetic-cms.dat"
        self.cipher.write_bytes(os.urandom(4096))
        self.cipher.chmod(0o600)
        self.receipt=self.root/"receipt.json"
        self.signature=self.root/"receipt.sig"
        self.now=1730000000
        self.nonce="SYNTHETIC_RECEIPT_"+("A"*32)
    def signed(self):
        return sign(self.cipher,self.private,self.public,
                    self.receipt,self.signature,now=self.now,
                    nonce=self.nonce)
    def verified(self,**args):
        kw={
          "ciphertext":self.cipher,
          "pub":self.public,
          "metadata":self.receipt,
          "signature":self.signature,
          "pinned_fingerprint":public_fingerprint(self.public),
          "now":self.now+1
        }
        kw.update(args)
        return verify(**kw)
    def test_valid_synthetic_only_does_not_release(self):
        body=self.signed()
        self.assertFalse(body["external_copy_verified"])
        proof=self.verified()
        self.assertTrue(proof["ed25519_signature_valid"])
        self.assertTrue(proof["cms_package_hash_matches"])
        self.assertFalse(proof["go_for_installation"])
        self.assertFalse(proof["issuer_credential_custody_verified"])
        self.assertEqual(64,self.signature.stat().st_size)
    def test_modified_ciphertext_denied(self):
        self.signed()
        altered=self.root/"altered.dat"
        b=bytearray(self.cipher.read_bytes());b[-9]^=1
        altered.write_bytes(b)
        with self.assertRaises(Rejected):
            self.verified(ciphertext=altered)
    def test_pinned_wrong_key_denied(self):
        self.signed()
        with self.assertRaises(Rejected):
            self.verified(pub=self.other_pub)
        with self.assertRaises(Rejected):
            self.verified(pinned_fingerprint="0"*64)
    def test_receipt_tamper_denied(self):
        self.signed()
        data=json.loads(self.receipt.read_text())
        data["nonce_ref"]="SYNTHETIC_RECEIPT_"+("B"*32)
        self.receipt.write_text(json.dumps(data,sort_keys=True,
                               separators=(",",":")))
        with self.assertRaises(Rejected):
            self.verified()
    def test_signature_corruption_denied(self):
        self.signed()
        b=bytearray(self.signature.read_bytes());b[5]^=4
        self.signature.write_bytes(b)
        with self.assertRaises(Rejected):
            self.verified()
    def test_expiry_and_future_time_denied(self):
        self.signed()
        with self.assertRaises(Rejected):
            self.verified(now=self.now+1201)
        with self.assertRaises(Rejected):
            self.verified(now=self.now-10)
    def test_false_go_and_extra_fields_denied(self):
        self.signed()
        data=json.loads(self.receipt.read_text())
        data["go_for_installation"]=True
        self.receipt.write_text(json.dumps(data,sort_keys=True,
                               separators=(",",":")))
        with self.assertRaises(Rejected):
            self.verified()
        data["go_for_installation"]=False
        data["operational_permission"]="synthetic"
        self.receipt.write_text(json.dumps(data,sort_keys=True,
                               separators=(",",":")))
        with self.assertRaises(Rejected):
            self.verified()
    def test_duplicate_json_key_denied(self):
        self.signed()
        self.receipt.write_bytes(
            self.receipt.read_bytes().replace(
                b'"schema":',b'"schema":"forged","schema":',1))
        with self.assertRaises(Rejected):
            self.verified()
    def test_public_key_and_receipt_symlink_denied(self):
        self.signed()
        linked=self.root/"linked-receipt.json"
        linked.symlink_to(self.receipt)
        with self.assertRaises(Rejected):
            self.verified(metadata=linked)
        pub=self.root/"linked-pub.pem"
        pub.symlink_to(self.public)
        with self.assertRaises(Rejected):
            self.verified(pub=pub)
    def test_private_signer_permissions_and_no_overwrite(self):
        self.private.chmod(0o644)
        with self.assertRaises(Rejected):
            self.signed()
        self.private.chmod(0o600)
        self.signed()
        with self.assertRaises(Rejected):
            self.signed()

if __name__=="__main__":
    unittest.main()
