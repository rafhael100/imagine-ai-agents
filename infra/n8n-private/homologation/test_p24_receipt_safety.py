"""P24 regressions for independently reproduced receipt/manifest failures."""
import hashlib
import io
import json
import os
import subprocess
import tarfile
import tempfile
import unittest
from pathlib import Path
from unittest import mock

import signed_receipt_p23 as receipts
from seal_backup import COMPONENTS, Denied, verify_decrypted
from test_p22_nested_tar import packaged_plaintext, tar_bytes
from test_p23_signed_receipt import signer


class ReceiptSafetyP24(unittest.TestCase):
    def setUp(self):
        temporary=tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        self.root=Path(temporary.name)
        self.private,self.public=signer(self.root,"trusted")
        self.cipher=self.root/"ciphertext.cms"
        self.cipher.write_bytes(b"synthetic-ciphertext"*32)
        self.cipher.chmod(0o600)
        self.metadata=self.root/"receipt.json"
        self.signature=self.root/"receipt.sig"
        self.now=1730000000

    def sign(self):
        return receipts.sign(self.cipher,self.private,self.public,
            self.metadata,self.signature,now=self.now,
            nonce="SYNTHETIC_RECEIPT_"+"A"*32)

    def verify(self):
        return receipts.verify(self.cipher,self.public,self.metadata,
            self.signature,pinned_fingerprint=receipts.public_fingerprint(self.public),
            now=self.now+1)

    def test_broken_symlink_outputs_are_preserved(self):
        for path in (self.metadata,self.signature):
            with self.subTest(output=path.name):
                missing=self.root/(path.name+".missing")
                path.symlink_to(missing)
                with self.assertRaises(receipts.Rejected):
                    self.sign()
                self.assertTrue(path.is_symlink())
                self.assertEqual(path.readlink(),missing)
                self.assertFalse(missing.exists())
                other=self.signature if path==self.metadata else self.metadata
                self.assertFalse(os.path.lexists(other))
                path.unlink()

    def test_competing_exclusive_creates_are_preserved(self):
        original=receipts.safe_write
        for competing in (self.metadata,self.signature):
            with self.subTest(output=competing.name):
                def race(path,payload):
                    if path==competing:
                        original(path,b"another producer owns this")
                    return original(path,payload)
                with mock.patch.object(receipts,"safe_write",side_effect=race):
                    with self.assertRaises(receipts.Rejected):
                        self.sign()
                self.assertEqual(competing.read_bytes(),b"another producer owns this")
                other=self.signature if competing==self.metadata else self.metadata
                self.assertFalse(os.path.lexists(other))
                competing.unlink()

    def test_cleanup_preserves_a_replacement_inode(self):
        original=receipts.call_openssl
        saved=self.root/"original-owned-inode"
        def replace_then_fail(args,*more,**kw):
            if args[0]=="pkeyutl":
                self.metadata.rename(saved)
                self.metadata.write_bytes(b"replacement owned by another producer")
                raise receipts.Rejected("SYNTHETIC_SIGN_FAILURE")
            return original(args,*more,**kw)
        with mock.patch.object(receipts,"call_openssl",side_effect=replace_then_fail):
            with self.assertRaises(receipts.Rejected):
                self.sign()
        self.assertEqual(self.metadata.read_bytes(),b"replacement owned by another producer")
        self.assertFalse(self.signature.exists())

    def test_failure_cleans_only_files_created_by_this_call(self):
        original=receipts.call_openssl
        def fail_sign(args,*more,**kw):
            if args[0]=="pkeyutl":
                raise receipts.Rejected("SYNTHETIC_SIGN_FAILURE")
            return original(args,*more,**kw)
        with mock.patch.object(receipts,"call_openssl",side_effect=fail_sign):
            with self.assertRaises(receipts.Rejected):
                self.sign()
        self.assertFalse(self.metadata.exists())
        self.assertFalse(self.signature.exists())

    def test_failed_write_cleans_its_partial_inode(self):
        path=self.root/"partial.json"
        with mock.patch.object(receipts.os,"fsync",side_effect=OSError("synthetic IO")):
            with self.assertRaises(OSError):
                receipts.safe_write(path,b"synthetic")
        self.assertFalse(path.exists())

    def test_mismatched_signer_keypair_denied_before_output(self):
        _,self.public=signer(self.root,"other")
        with self.assertRaisesRegex(receipts.Rejected,"SIGNING_KEYPAIR_MISMATCH"):
            self.sign()
        self.assertFalse(self.metadata.exists())
        self.assertFalse(self.signature.exists())

    def test_non_ed25519_signer_denied(self):
        private=self.root/"rsa.key"
        public=self.root/"rsa.pub"
        subprocess.run(["openssl","genpkey","-algorithm","RSA","-pkeyopt",
            "rsa_keygen_bits:2048","-out",str(private)],capture_output=True,check=True)
        subprocess.run(["openssl","pkey","-in",str(private),"-pubout",
            "-out",str(public)],capture_output=True,check=True)
        with self.assertRaisesRegex(receipts.Rejected,"ED25519_SIGNER_REQUIRED"):
            receipts.public_fingerprint(public)

    def test_resigned_numeric_false_flags_denied(self):
        receipt=self.sign()
        self.assertTrue(self.verify()["ed25519_signature_valid"])
        for field in receipts.FALSE_FLAGS:
            for value in (0,0.0,None,"false",[]):
                with self.subTest(field=field,value=value):
                    altered=dict(receipt);altered[field]=value
                    self.metadata.write_bytes(receipts.canonical(altered))
                    signature=receipts.call_openssl(["pkeyutl","-sign","-rawin",
                        "-inkey",str(self.private),"-in",str(self.metadata)])
                    self.signature.write_bytes(signature)
                    with self.assertRaises(receipts.Rejected):
                        self.verify()


class ManifestSafetyP24(unittest.TestCase):
    def setUp(self):
        temporary=tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        self.path=Path(temporary.name)/"quarantine.tar"
        baseline=packaged_plaintext(tar_bytes([{"name":"config"}]))
        with tarfile.open(fileobj=io.BytesIO(baseline),mode="r:") as archive:
            self.parts={entry.name:archive.extractfile(entry).read() for entry in archive}

    def write(self,*,change_manifest=None,change_parts=None,raw_manifest=None,
              member_mode=0o600):
        parts=dict(self.parts)
        if change_parts:
            parts.update(change_parts)
        manifest=json.loads(parts["manifest.json"])
        manifest["components"]={
            name:{"sha256":hashlib.sha256(parts[name]).hexdigest(),"bytes":len(parts[name])}
            for name in COMPONENTS
        }
        if change_manifest:
            change_manifest(manifest)
        data=json.dumps(manifest,sort_keys=True).encode()
        parts["manifest.json"]=raw_manifest(data) if raw_manifest else data
        with tarfile.open(self.path,"w") as archive:
            for name,data in parts.items():
                entry=tarfile.TarInfo(name);entry.size=len(data);entry.mode=member_mode
                archive.addfile(entry,io.BytesIO(data))

    def test_valid_package_remains_non_operational(self):
        self.write()
        proof=verify_decrypted(self.path)
        self.assertTrue(proof["component_hashes_match"])
        self.assertFalse(proof["database_restore_verified"])
        self.assertFalse(proof["go_for_installation"])

    def test_duplicate_manifest_keys_at_each_depth_denied(self):
        for key in (b'schema',b'components',b'n8n-pg.dump',b'bytes'):
            with self.subTest(key=key):
                needle=b'"'+key+b'":'
                self.write(raw_manifest=lambda data:data.replace(
                    needle,needle+b'"untrusted-duplicate",'+needle,1))
                with self.assertRaises(Denied):
                    verify_decrypted(self.path)

    def test_component_byte_counts_require_integers(self):
        def floats(manifest):
            for component in manifest["components"].values():
                component["bytes"]=float(component["bytes"])
        self.write(change_manifest=floats)
        with self.assertRaises(Denied):
            verify_decrypted(self.path)

    def test_matching_manifest_cannot_allow_plain_sql_or_empty_dump(self):
        for name in ("n8n-pg.dump","crm-service.dump","agents-service.dump"):
            for payload in (b"plain sql",b""):
                with self.subTest(name=name,payload=payload):
                    self.write(change_parts={name:payload})
                    with self.assertRaises(Denied):
                        verify_decrypted(self.path)

    def test_matching_manifest_cannot_allow_invalid_key_length(self):
        for size in (0,1,31,257):
            with self.subTest(size=size):
                self.write(change_parts={"n8n-encryption.key":b"k"*size})
                with self.assertRaises(Denied):
                    verify_decrypted(self.path)
        for size in (32,256):
            with self.subTest(valid_size=size):
                self.write(change_parts={"n8n-encryption.key":b"k"*size})
                self.assertTrue(verify_decrypted(self.path)["component_hashes_match"])

    def test_component_manifest_schema_is_closed(self):
        def extra(manifest):
            manifest["components"]["n8n-pg.dump"]["trusted"]=True
        self.write(change_manifest=extra)
        with self.assertRaises(Denied):
            verify_decrypted(self.path)

    def test_unsafe_outer_permissions_denied(self):
        for mode in (0o644,0o777,0o4600,0o2600):
            with self.subTest(mode=oct(mode)):
                self.write(member_mode=mode)
                with self.assertRaises(Denied):
                    verify_decrypted(self.path)


if __name__=="__main__":
    unittest.main()
