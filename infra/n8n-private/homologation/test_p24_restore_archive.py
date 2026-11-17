"""P24 restore boundary: real files/archives, no Docker or credential mocks."""
import io
import stat
import tarfile
import tempfile
import unittest
from pathlib import Path

from check_p24_full_n8n_restore import materialize_verified_package, normalized_volume
from seal_backup import COMPONENTS, Denied
from test_p22_nested_tar import packaged_plaintext, tar_bytes


class RestoreArchiveTests(unittest.TestCase):
    def test_only_verified_components_materialize_as_private_regular_files(self):
        volume = tar_bytes([{"name": "./", "kind": "dir"},
                            {"name": "./config", "payload": b"synthetic-config"}])
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            package = root / "package.tar"
            package.write_bytes(packaged_plaintext(volume))
            destination = root / "new-components"
            proof = materialize_verified_package(package, destination)
            self.assertFalse(proof["go_for_installation"])
            self.assertEqual(set(p.name for p in destination.iterdir()), set(COMPONENTS))
            self.assertEqual(stat.S_IMODE(destination.stat().st_mode), 0o700)
            self.assertEqual((destination / "n8n-data.tar").read_bytes(), volume)
            for path in destination.iterdir():
                self.assertTrue(stat.S_ISREG(path.lstat().st_mode))
                self.assertEqual(stat.S_IMODE(path.stat().st_mode), 0o600)

    def test_existing_destination_and_contents_are_preserved(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            package = root / "package.tar"
            package.write_bytes(packaged_plaintext(tar_bytes([{"name": "config"}])))
            destination = root / "occupied"
            destination.mkdir()
            existing = destination / "n8n-encryption.key"
            existing.write_bytes(b"DO_NOT_TOUCH")
            with self.assertRaises(FileExistsError):
                materialize_verified_package(package, destination)
            self.assertEqual(existing.read_bytes(), b"DO_NOT_TOUCH")
            self.assertEqual(list(destination.iterdir()), [existing])

    def test_matching_manifest_with_hostile_volume_creates_no_destination(self):
        for attack in ([{"name": "../escape"}], [{"name": "config", "kind": "symlink"}]):
            with self.subTest(attack=attack), tempfile.TemporaryDirectory() as tmp:
                root = Path(tmp)
                package = root / "package.tar"
                package.write_bytes(packaged_plaintext(tar_bytes(attack)))
                destination = root / "not-created"
                with self.assertRaises(Denied):
                    materialize_verified_package(package, destination)
                self.assertFalse(destination.exists())
                self.assertFalse((root / "escape").exists())

    def test_normalized_archive_preserves_bytes_but_never_archive_privileges(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            source = root / "volume.tar"
            source.write_bytes(tar_bytes([
                {"name": "./", "kind": "dir", "mode": 0o777},
                {"name": "./workflows/", "kind": "dir", "mode": 0o777},
                {"name": "./workflows/fixture.json", "mode": 0o777, "payload": b"SYNTHETIC_ONLY"},
            ]))
            destination = root / "normalized.tar"
            normalized_volume(source, destination)
            self.assertEqual(stat.S_IMODE(destination.stat().st_mode), 0o600)
            with tarfile.open(destination, "r:") as archive:
                entries = archive.getmembers()
                self.assertEqual([e.name for e in entries], [".", "workflows", "workflows/fixture.json"])
                for entry in entries:
                    self.assertEqual((entry.uid, entry.gid), (1000, 1000))
                    self.assertEqual(entry.mode, 0o700 if entry.isdir() else 0o600)
                    self.assertFalse(entry.issym() or entry.islnk())
                self.assertEqual(archive.extractfile(entries[-1]).read(), b"SYNTHETIC_ONLY")

    def test_normalization_refuses_hostile_input_before_creating_output(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            source = root / "hostile.tar"
            source.write_bytes(tar_bytes([{"name": "link", "kind": "hardlink"}]))
            destination = root / "must-not-exist.tar"
            with self.assertRaises(Denied):
                normalized_volume(source, destination)
            self.assertFalse(destination.exists())


if __name__ == "__main__":
    unittest.main()
