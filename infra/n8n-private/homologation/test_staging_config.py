from pathlib import Path
import copy
import json
import os
import subprocess
import unittest

from check_staging_config import N8N_IMAGE, verify


class StagingConfigTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        root = Path(__file__).resolve().parents[1]
        env = os.environ | dict(
            N8N_IMAGE=N8N_IMAGE,
            N8N_PG_IMAGE="postgres@sha256:" + "b" * 64,
            N8N_PG_ADMIN_PASSWORD_FILE="/test/bootstrap",
            N8N_PG_PASSWORD_FILE="/test/runtime",
            N8N_ENCRYPTION_KEY_FILE="/test/n8n-key",
        )
        cmd = ["docker", "compose", "-f", "docker-compose.yml", "-f",
               "docker-compose.staging.yml", "--profile", "homologation",
               "config", "--format", "json"]
        cls.config = json.loads(subprocess.check_output(cmd, cwd=root, env=env))
        subprocess.run(
            ["python3", "check_compose_static.py"], cwd=root, env=env,
            input=json.dumps(cls.config), text=True, check=True,
        )

    def test_rendered_configuration(self):
        verify(self.config)

    def test_public_port_rejected(self):
        config = copy.deepcopy(self.config)
        config["services"]["n8n"]["ports"] = [{"target": 5678}]
        with self.assertRaises(ValueError):
            verify(config)

    def test_reused_existing_volume_rejected(self):
        config = copy.deepcopy(self.config)
        config["volumes"]["n8n_data"]["external"] = True
        with self.assertRaises(ValueError):
            verify(config)

    def test_mutable_image_rejected(self):
        config = copy.deepcopy(self.config)
        config["services"]["n8n-db"]["image"] = "postgres:17"
        with self.assertRaises(ValueError):
            verify(config)

    def test_execution_data_retention_rejected(self):
        config = copy.deepcopy(self.config)
        config["services"]["n8n"]["environment"]["EXECUTIONS_DATA_SAVE_ON_ERROR"] = "all"
        with self.assertRaises(ValueError):
            verify(config)


    def test_boot_requires_postgresql_readiness(self):
        config = copy.deepcopy(self.config)
        config["services"]["n8n"]["depends_on"]["n8n-db"]["condition"] = "service_started"
        with self.assertRaises(ValueError):
            verify(config)

    def test_independent_restart_policy_is_rejected(self):
        config = copy.deepcopy(self.config)
        config["services"]["n8n"]["restart"] = "always"
        with self.assertRaises(ValueError):
            verify(config)

    def test_initdb_socket_is_not_valid_readiness(self):
        config = copy.deepcopy(self.config)
        test = config["services"]["n8n-db"]["healthcheck"]["test"]
        del test[2:4]
        with self.assertRaises(ValueError):
            verify(config)
