"""The recurring staging publisher must fail before any transport without its own pair."""
import os
import tempfile
import unittest
from unittest.mock import patch

import publish_snapshot


class StagingBindingsTest(unittest.TestCase):
    def test_missing_or_partial_pair_never_uses_pilot_values(self):
        with tempfile.TemporaryDirectory() as spool:
            settings = {
                "STAGING_SPOOL": spool,
                "STAGING_R2_EU_ENDPOINT": "https://" + "1" * 32 + ".eu.r2.cloudflarestorage.com",
                "COMPOSE_PROJECT_NAME": "imagine-n8n-stage-0123456789ab",
                "AWS_ACCESS_KEY_ID": "pilot-fixture",
                "AWS_SECRET_ACCESS_KEY": "pilot-fixture",
            }
            for partial in ({}, {"N8N_STAGING_R2_ACCESS_KEY_ID": "stage-fixture"}):
                with self.subTest(partial=bool(partial)), patch.dict(
                    os.environ, settings | partial, clear=True
                ), patch.object(publish_snapshot.subprocess, "run") as transport:
                    with self.assertRaisesRegex(KeyError, "N8N_STAGING_R2_"):
                        publish_snapshot.main()
                    transport.assert_not_called()
