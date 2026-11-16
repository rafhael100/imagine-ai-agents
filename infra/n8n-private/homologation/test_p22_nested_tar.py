"""P22: hostile nested n8n volume TARs, including a forged matching manifest."""
import hashlib
import io
import json
import tarfile
import tempfile
import unittest
from pathlib import Path

from seal_backup import COMPONENTS, Denied, inspect_volume, verify_decrypted


def tar_bytes(members):
    out=io.BytesIO()
    with tarfile.open(fileobj=out,mode="w") as tar:
        for item in members:
            name=item["name"]
            kind=item.get("kind","file")
            t=tarfile.TarInfo(name)
            if kind=="file":
                data=item.get("payload",b"synthetic")
                t.size=len(data);t.mode=item.get("mode",0o600)
                tar.addfile(t,io.BytesIO(data))
            else:
                t.type={"dir":tarfile.DIRTYPE,
                        "symlink":tarfile.SYMTYPE,
                        "hardlink":tarfile.LNKTYPE,
                        "device":tarfile.CHRTYPE}[kind]
                t.mode=item.get("mode",0o700)
                t.linkname="outside"
                tar.addfile(t)
    return out.getvalue()


def packaged_plaintext(volume):
    source={
       "n8n-pg.dump":b"PGDMP_SYNTHETIC_P22",
       "n8n-data.tar":volume,
       "n8n-encryption.key":b"a"*64,
       "crm-service.dump":b"PGDMP_SYNTHETIC_CRM",
       "agents-service.dump":b"PGDMP_SYNTHETIC_AGENTS",
    }
    manifest={
       "schema":"IMAGINE_RECOVERY_PACKAGE_V1",
       "components":{
          k:{"sha256":hashlib.sha256(v).hexdigest(),"bytes":len(v)}
          for k,v in source.items()
       },
       "recipient_fingerprint":"a"*64,
       "coherent_source_snapshot_verified":False,
       "external_copy_verified":False,
       "application_restore_verified":False,
    }
    source["manifest.json"]=json.dumps(manifest,sort_keys=True).encode()
    out=io.BytesIO()
    with tarfile.open(fileobj=out,mode="w") as tar:
        for name,value in source.items():
            info=tarfile.TarInfo(name);info.size=len(value);info.mode=0o600
            tar.addfile(info,io.BytesIO(value))
    return out.getvalue()


class NestedTarTests(unittest.TestCase):
    def test_normal_paths_and_stream_are_allowed(self):
        contents=tar_bytes([
          {"name":"./","kind":"dir"},
          {"name":"./workflows/","kind":"dir"},
          {"name":"./workflows/n8n.json"},
          {"name":"meta.txt"},
        ])
        inspect_volume(io.BytesIO(contents))
        with tempfile.TemporaryDirectory() as d:
            p=Path(d)/"valid.tar"
            p.write_bytes(contents)
            inspect_volume(p)
            outer=Path(d)/"package.tar"
            outer.write_bytes(packaged_plaintext(contents))
            result=verify_decrypted(outer)
            self.assertTrue(result["component_hashes_match"])
            self.assertTrue(result["volume_tar_safe_for_reviewed_extraction"])
            self.assertFalse(result["go_for_installation"])

    def test_path_and_type_attacks_denied(self):
        attacks={
          "duplicate":[{"name":"repeat"},{"name":"repeat"}],
          "alias_duplicate":[{"name":"./same"},{"name":"same"}],
          "parent_file":[{"name":"parent"},{"name":"parent/child"}],
          "child_then_parent_file":[{"name":"parent/child"},{"name":"parent"}],
          "traversal":[{"name":"../escape"}],
          "nested_traversal":[{"name":"ok/../escape"}],
          "absolute":[{"name":"/etc/passwd"}],
          "double_slash":[{"name":"foo//bar"}],
          "backslash":[{"name":"foo\\bar"}],
          "colon":[{"name":"C:/evil"}],
          "too_long":[{"name":"x"*241}],
          "symlink":[{"name":"link","kind":"symlink"}],
          "hardlink":[{"name":"hard","kind":"hardlink"}],
          "device":[{"name":"dev","kind":"device"}],
          "suid":[{"name":"evil","mode":0o4600}],
          "root_file":[{"name":"./"}],
        }
        for name,entries in attacks.items():
            with self.subTest(name=name):
                volume=tar_bytes(entries)
                with self.assertRaises(Denied):
                    inspect_volume(io.BytesIO(volume))

    def test_forged_matching_manifest_cannot_authorize_bad_volume(self):
        for name,entries in (
            ("duplicate",[{"name":"x"},{"name":"x"}]),
            ("traversal",[{"name":"../private-key"}]),
            ("symlink",[{"name":"x","kind":"symlink"}]),
        ):
            with self.subTest(name=name),tempfile.TemporaryDirectory() as d:
                outer=Path(d)/"forged-manifest-matching.tar"
                # The attacker fully controls this manifest. SHA-256 matches
                # the attack TAR, demonstrating why a hash alone is insufficient.
                outer.write_bytes(packaged_plaintext(tar_bytes(entries)))
                with self.assertRaises(Denied):
                    verify_decrypted(outer)

    def test_outer_manifest_rejects_extra_or_duplicate_members(self):
        volume=tar_bytes([{"name":"normal"}])
        with tempfile.TemporaryDirectory() as d:
            p=Path(d)/"outer.tar"
            p.write_bytes(packaged_plaintext(volume))
            self.assertTrue(verify_decrypted(p)["component_hashes_match"])
            with tarfile.open(p,"a") as f:
                extra=b"secret"
                item=tarfile.TarInfo("not-reviewed");item.size=len(extra)
                f.addfile(item,io.BytesIO(extra))
            with self.assertRaises(Denied):
                verify_decrypted(p)


if __name__=="__main__":
    unittest.main()
