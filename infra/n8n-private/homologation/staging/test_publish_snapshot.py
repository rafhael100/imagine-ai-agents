"""New failure mode: R2 committed the PUT, then the worker lost its receipt."""
import hashlib
import json
import os
import tempfile
import unittest
from datetime import datetime, timezone
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

import publish_snapshot


class PublishSnapshotTest(unittest.TestCase):
    def test_retry_verifies_remote_object_before_recording_receipt(self):
        with tempfile.TemporaryDirectory() as folder:
            spool = Path(folder)
            day = datetime.now(timezone.utc).date().isoformat()
            capture = spool / f"{day}.tar.gz.age"
            receipt = spool / f"{day}.r2.json"
            payload = b"synthetic ciphertext"
            capture.write_bytes(payload)
            settings = {
                "COMPOSE_PROJECT_NAME": "imagine-n8n-stage-0123456789ab",
                "STAGING_SPOOL": folder,
                "STAGING_R2_EU_ENDPOINT": "https://" + "1" * 32 + ".eu.r2.cloudflarestorage.com",
                "STAGING_MAX_SNAPSHOT_BYTES": "1024",
                "N8N_STAGING_R2_ACCESS_KEY_ID": "staging-fixture",
                "N8N_STAGING_R2_SECRET_ACCESS_KEY": "staging-fixture-secret",
            }
            modes = iter([b"corrupted contents!", payload])

            def transport(argv, *, input, **kwargs):
                self.assertIn("aws:amz:auto:s3", argv)
                self.assertIn("staging-fixture:staging-fixture-secret", input)
                self.assertIn("/imagine-n8n-backups-testes/staging/daily/", argv[-1])
                method = argv[argv.index("-X") + 1]
                if method == "PUT":
                    self.assertIn("If-None-Match: *", argv)
                    return SimpleNamespace(returncode=0, stdout="412")
                Path(argv[argv.index("-o") + 1]).write_bytes(next(modes))
                return SimpleNamespace(returncode=0, stdout="200")

            previous_umask = os.umask(0o077)
            try:
                with patch.dict(os.environ, settings, clear=True), patch.object(
                    publish_snapshot.subprocess, "run", side_effect=transport
                ) as request:
                    with self.assertRaisesRegex(RuntimeError, "size or SHA-256"):
                        publish_snapshot.main()
                    self.assertFalse(receipt.exists())
                    publish_snapshot.main()
                    self.assertEqual(json.loads(receipt.read_text())["sha256"], hashlib.sha256(payload).hexdigest())
                    self.assertEqual(request.call_count, 4)
                    publish_snapshot.main()
                    self.assertEqual(request.call_count, 4)
            finally:
                os.umask(previous_umask)


if __name__ == "__main__":
    unittest.main()
