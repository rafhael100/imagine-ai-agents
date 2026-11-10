"""Regression suite: seven bilateral CRM review decisions remain NO-GO.

Never treats a local CSV, draft proposal, generated JSON or green CI as approval.
"""
from __future__ import annotations

import csv
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
import generate_op01_bilateral_packet as packet
import check_r01_readiness as readiness

SCRIPT = ROOT / "tools/generate_op01_bilateral_packet.py"
OUTPUT = ROOT / "docs/evidencias/op01-pacote-aceite-bilateral.json"


class BilateralPacketTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        root = Path(self.temp.name)
        self.output = root / "packet.json"
        self.ledger = root / "gates.csv"
        self.cases = root / "cases.csv"
        self.manifest = root / "manifest.json"
        self.output.write_bytes(OUTPUT.read_bytes())
        self.ledger.write_bytes(readiness.GATE_FILE.read_bytes())
        self.cases.write_bytes(readiness.CASE_FILE.read_bytes())
        self.manifest.write_bytes(packet.parity.MANIFEST.read_bytes())

    def run_cli(self, *extra):
        result = subprocess.run(
            [sys.executable, str(SCRIPT),
             "--output", str(self.output),
             "--ledger", str(self.ledger),
             "--cases", str(self.cases),
             "--manifest", str(self.manifest), *extra],
            capture_output=True, text=True, timeout=10, check=False,
        )
        report = json.loads(result.stdout)
        self.assertEqual(report["decisao_piloto"], "NO_GO")
        self.assertFalse(report["permite_credenciais"])
        self.assertFalse(report["permite_deploy"])
        self.assertEqual(report["chamadas_externas"], 0)
        return result.returncode, report

    def test_pinned_current_baseline_is_byte_identical_and_still_no_go(self):
        expected = packet.canonical_bytes(packet.compile_packet())
        self.assertEqual(OUTPUT.read_bytes(), expected)
        status, summary = self.run_cli("--check")
        self.assertEqual(status, 0)
        self.assertTrue(summary["pacote_valido"])
        data = json.loads(expected)
        self.assertEqual(data["decisao_piloto"], "NO_GO")
        self.assertEqual(data["fonte_crm"]["pr"], 57)
        self.assertEqual(data["fonte_crm"]["estado"], "DRAFT_NOT_APPROVED")
        self.assertEqual(data["contagens"], {
            "gates": 14, "gates_abertos": 14, "gates_em_revisao": 0,
            "cenarios": 13, "cenarios_nao_executados": 13,
            "decisoes_pendentes": 7,
        })
        self.assertEqual([d["id"] for d in data["decisoes"]],
                         [f"OP01-R{i:02}" for i in range(1, 8)])
        self.assertTrue(all(d["aprovado"] is False and
                            d["estado"] == "ABERTO_SEM_ACEITE"
                            for d in data["decisoes"]))
        self.assertEqual({g for d in data["decisoes"] for g in d["gates"]},
                         set(readiness.REQUIRED_GATES))
        self.assertEqual({c["id"] for d in data["decisoes"]
                          for c in d["cenarios_homologacao"]},
                         set(readiness.REQUIRED_CASES))
        self.assertTrue(all(c["estado"] == "NAO_EXECUTADO"
                            for d in data["decisoes"] for c in d["cenarios_homologacao"]))

    def test_tampered_committed_packet_fails_closed_without_rewriting(self):
        original = json.loads(self.output.read_text(encoding="utf-8"))
        original["decisao_piloto"] = "GO"
        self.output.write_text(json.dumps(original), encoding="utf-8")
        before = self.output.read_bytes()
        status, summary = self.run_cli("--check")
        self.assertEqual(status, 1)
        self.assertEqual(summary["codigo"], "REVIEW_PACKET_OUT_OF_SYNC")
        self.assertEqual(self.output.read_bytes(), before)

    def test_marking_case_executed_cannot_create_approval(self):
        with self.cases.open(encoding="utf-8", newline="") as stream:
            rows = list(csv.DictReader(stream))
        rows[0]["estado_execucao"] = "PASS"
        with self.cases.open("w", encoding="utf-8", newline="") as stream:
            writer = csv.DictWriter(stream, fieldnames=readiness.CASE_FIELDS)
            writer.writeheader()
            writer.writerows(rows)
        status, report = self.run_cli("--write")
        self.assertEqual(status, 1)
        self.assertFalse(report["pacote_valido"])
        self.assertEqual(report["codigo"], "GATE_OR_CASE_REGISTER_INVALID")
        self.assertEqual(self.output.read_bytes(), OUTPUT.read_bytes())

    def test_marking_gate_approved_cannot_create_approval(self):
        with self.ledger.open(encoding="utf-8", newline="") as stream:
            rows = list(csv.DictReader(stream))
        rows[0]["estado"] = "APROVADO"
        with self.ledger.open("w", encoding="utf-8", newline="") as stream:
            writer = csv.DictWriter(stream, fieldnames=readiness.GATE_FIELDS)
            writer.writeheader()
            writer.writerows(rows)
        status, report = self.run_cli("--check")
        self.assertEqual(status, 1)
        self.assertEqual(report["codigo"], "GATE_OR_CASE_REGISTER_INVALID")

    def test_draft_blob_or_review_head_change_is_not_silent(self):
        manifest = json.loads(self.manifest.read_text(encoding="utf-8"))
        manifest["source_pr_head"] = "f" * 40
        self.manifest.write_text(json.dumps(manifest), encoding="utf-8")
        status, report = self.run_cli("--check")
        self.assertEqual(status, 1)
        self.assertEqual(report["codigo"], "CRM_PROPOSAL_INVALID")

    def test_under_review_does_not_turn_into_pilot_go(self):
        with self.ledger.open(encoding="utf-8", newline="") as stream:
            rows = list(csv.DictReader(stream))
        rows[0]["estado"] = "EM_REVISAO"
        with self.ledger.open("w", encoding="utf-8", newline="") as stream:
            writer = csv.DictWriter(stream, fieldnames=readiness.GATE_FIELDS)
            writer.writeheader()
            writer.writerows(rows)
        data = packet.compile_packet(ledger=self.ledger)
        self.assertEqual(data["decisao_piloto"], "NO_GO")
        self.assertEqual(data["contagens"]["gates_em_revisao"], 1)
        self.assertEqual(data["decisoes"][0]["estado"], "EM_REVISAO_SEM_ACEITE")
        self.assertFalse(data["decisoes"][0]["aprovado"])

    def test_write_is_deterministic_without_network_or_credentials(self):
        self.output.unlink()
        status, data = self.run_cli("--write")
        self.assertEqual(status, 0, data)
        first = self.output.read_bytes()
        status, data = self.run_cli("--write")
        self.assertEqual(status, 0, data)
        self.assertEqual(self.output.read_bytes(), first)
        self.assertEqual(first, OUTPUT.read_bytes())
        self.assertNotIn(b'"decisao_piloto": "GO"', first)


if __name__ == "__main__":
    unittest.main()
