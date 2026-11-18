"""Offline compatibility counterproofs using the actual pinned CRM modules.

No CI identity, database, container or native execution is simulated as evidence.
"""
from __future__ import annotations

import contextlib
import copy
import os
from pathlib import Path
import sys
import tempfile
from types import ModuleType, SimpleNamespace
import unittest

import crm_feedback_backup as bridge


class CrmFeedbackBackupTests(unittest.TestCase):
    def setUp(self):
        raw = os.environ.get("AIA_CRM_FEEDBACK_SOURCE", "")
        self.source = Path(raw)
        self.assertTrue(raw and self.source.is_absolute() and self.source.is_dir(),
                        "AIA_CRM_FEEDBACK_SOURCE must identify the pinned CRM archive")
        self.saved = {name: sys.modules.pop(name) for name in bridge.MODULES if name in sys.modules}
        self.addCleanup(self.restore_modules)

    def restore_modules(self):
        for name in bridge.MODULES:
            sys.modules.pop(name, None)
        sys.modules.update(self.saved)

    def test_real_modules_use_one_canonical_source_and_required_catalog_api(self):
        import inspect
        contract, snapshot = bridge.load_crm_backup(self.source)
        for name in bridge.MODULES:
            self.assertEqual(Path(sys.modules[name].__file__).resolve(),
                             (self.source / "scripts/ops" / (name + ".py")).resolve())
        self.assertIs(snapshot.require, contract.require)
        self.assertIs(contract.catalog_manifest, sys.modules[bridge.MODULES[0]].catalog_manifest)
        parameter = inspect.signature(contract.verify_restored_security).parameters["source_catalog"]
        self.assertEqual(parameter.kind, inspect.Parameter.KEYWORD_ONLY)
        self.assertIs(parameter.default, inspect.Parameter.empty)
        self.assertIs(bridge.load_crm_backup(self.source)[0], contract)

    def test_foreign_preloaded_module_is_denied_without_erasing_it(self):
        for name in bridge.MODULES:
            with self.subTest(name=name):
                foreign = ModuleType(name)
                foreign.__file__ = "/unrelated-archive/" + name + ".py"
                sys.modules[name] = foreign
                with self.assertRaisesRegex(RuntimeError, "FEEDBACK_BACKUP_MODULE_ALREADY_LOADED_ELSEWHERE"):
                    bridge.load_crm_backup(self.source)
                self.assertIs(sys.modules[name], foreign)
                self.assertEqual(set(bridge.MODULES) & set(sys.modules), {name})
                sys.modules.pop(name)

    def test_missing_or_symlinked_module_is_denied_and_partial_imports_are_removed(self):
        with tempfile.TemporaryDirectory() as temporary:
            source = Path(temporary)
            ops = source / "scripts/ops"
            ops.mkdir(parents=True)
            for name in bridge.MODULES:
                (ops / (name + ".py")).write_bytes((self.source / "scripts/ops" / (name + ".py")).read_bytes())
            target = ops / "private_crm_backup_snapshot.py"
            target.unlink()
            for symlink in (False, True):
                if symlink:
                    target.symlink_to(self.source / "scripts/ops/private_crm_backup_snapshot.py")
                with self.assertRaisesRegex(RuntimeError, "FEEDBACK_BACKUP_MODULE_SOURCE"):
                    bridge.load_crm_backup(source)
                self.assertFalse(set(bridge.MODULES) & set(sys.modules))

    def test_real_closed_catalog_rejects_a_same_metadata_check_true(self):
        contract, _ = bridge.load_crm_backup(self.source)
        catalog = sys.modules[bridge.MODULES[0]]
        definition = "CHECK (((state)::text = ANY ((ARRAY['todo'::character varying, 'done'::character varying, 'cancelled'::character varying])::text[])))"
        source = {"database_collation": {"encoding": "UTF8"}, "constraints": [{
            "table": "public.crm_private_tasks", "name": "crm_private_tasks_state_check", "type": "c",
            "deferrable": False, "deferred": False, "validated": True, "definition": definition}],
            "columns": [{"table": "public.crm_private_tasks", "name": "state",
                "type": "character varying(16)", "collation": "pg_catalog.default", "not_null": True}]}
        fields = ("table", "name", "type", "validated", "definition")
        security = lambda value: {"constraints": [{field: row[field] for field in fields}
                                                  for row in value["constraints"]]}
        exact = catalog.security_catalog_comparison(security(source), security(source), source, source)
        self.assertEqual(exact["rule"], "EXACT")
        allowed = copy.deepcopy(source)
        allowed["constraints"][0]["definition"] = "CHECK (((state)::text = ANY (ARRAY[('todo'::character varying)::text, ('done'::character varying)::text, ('cancelled'::character varying)::text])))"
        self.assertEqual(catalog.security_catalog_comparison(security(source), security(allowed), source, allowed)["equivalences"], 1)
        changed = copy.deepcopy(source)
        changed["constraints"][0]["definition"] = "CHECK (true)"
        before, after = security(source), security(changed)
        # The old definition-dropping projection accepts this exact mutation.
        old = lambda value: [{k: v for k, v in row.items() if k != "definition"} for row in value["constraints"]]
        self.assertEqual(old(before), old(after))
        self.assertIsNone(catalog.security_catalog_comparison(before, after, source, changed))
        self.assertIsNone(catalog.security_catalog_comparison(before, before, source, changed))
        with self.assertRaises(TypeError):
            contract.verify_restored_security(lambda _: self.fail("missing catalog must fail before querying"), {}, {}, [])

    def capture_fixture(self, failure=None):
        calls = []
        query = object()
        profile = {"name": "synthetic-private-s2s-execution-monitor-v1", "execution_monitor": True,
                   "tables": ["public.crm_private_tasks"]}

        def detect(received, tables):
            self.assertIs(received, query)
            self.assertEqual(tables, ["crm_private_tasks"])
            calls.append("profile")
            return profile, {"security": "captured"}

        def catalog(received, *, schemas):
            self.assertIs(received, query)
            self.assertEqual(schemas, ("public", "crm_auto_service"))
            calls.append("catalog")
            if failure == "catalog":
                raise RuntimeError("SYNTHETIC_CATALOG_FAILURE")
            return {"catalog": "captured"}

        def tables(received, inventory):
            self.assertIs(received, query)
            self.assertIs(inventory, profile["tables"])
            calls.append("rows")
            return {"rows": "captured"}

        def dump():
            calls.append("dump")
            return b"invalid" if failure == "dump" else b"PGDMPsynthetic-unit-bytes"

        @contextlib.contextmanager
        def exported(command):
            self.assertEqual(command, ["docker", "exec", "-i", "-e", "PGPORT=5433", "a" * 64])
            calls.append("open")
            try:
                yield SimpleNamespace(query=query, dump=dump, exported_at="2026-11-18T00:00:00+00:00")
            finally:
                calls.append("closed")

        return calls, SimpleNamespace(detect_profile=detect, catalog_manifest=catalog), SimpleNamespace(exported_snapshot=exported), tables

    def test_catalog_security_rows_and_dump_share_one_exported_snapshot(self):
        calls, backup, snapshot, tables = self.capture_fixture()
        captured = bridge.capture_crm_backup(backup, snapshot, "a" * 64, ["crm_private_tasks"], tables)
        self.assertEqual(calls, ["open", "profile", "catalog", "rows", "dump", "closed"])
        self.assertEqual(captured["catalog"], {"catalog": "captured"})
        self.assertEqual(captured["snapshot_exported_at_utc"], "2026-11-18T00:00:00+00:00")

    def test_capture_failure_closes_exporter_and_never_returns_partial_backup(self):
        for failure in ("catalog", "dump"):
            with self.subTest(failure=failure):
                calls, backup, snapshot, tables = self.capture_fixture(failure)
                with self.assertRaises(RuntimeError):
                    bridge.capture_crm_backup(backup, snapshot, "a" * 64, ["crm_private_tasks"], tables)
                self.assertEqual(calls[-1], "closed")
                if failure == "catalog":
                    self.assertNotIn("dump", calls)

    def test_invalid_container_identity_is_denied_before_opening_snapshot(self):
        for container in ("production", "imagine-feedback-123-1-crm-src", "--flag", "", "a" * 63, "a" * 65, "g" * 64):
            calls, backup, snapshot, tables = self.capture_fixture()
            with self.subTest(container=container), self.assertRaisesRegex(RuntimeError, "FEEDBACK_BACKUP_CONTAINER_SCOPE"):
                bridge.capture_crm_backup(backup, snapshot, container, ["crm_private_tasks"], tables)
            self.assertEqual(calls, [])


if __name__ == "__main__":
    unittest.main()
