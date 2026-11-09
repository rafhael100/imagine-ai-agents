"""Offline differential tests for source-only PHP effect isolation scanner."""
import json
import subprocess
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCANNER = ROOT / "tools/check_synthetic_php_isolation.php"


class SyntheticIsolationTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.dir = Path(self.tmp.name)

    def scan(self, source):
        (self.dir / "fixture.php").write_text(source, encoding="utf-8")
        process = subprocess.run(
            ["php", str(SCANNER), str(self.dir)],
            capture_output=True, text=True, timeout=10,
        )
        report = json.loads(process.stdout)
        self.assertEqual(report["operational_decision"], "NO_GO")
        self.assertFalse(report["authorizes_deploy"])
        self.assertFalse(report["authorizes_credentials"])
        self.assertEqual(report["network_calls"], 0)
        self.assertNotIn("fixture.php", process.stdout)
        return process.returncode, report

    def test_inert_basic_functions(self):
        code, report = self.scan("<?php function safe(int $a): int { return $a + 1; }\n")
        self.assertEqual(code, 0)
        self.assertTrue(report["isolation_check_passed"])
        self.assertEqual(report["files_scanned"], 1)

    def test_comments_and_strings_do_not_flag_external_calls(self):
        php = '''<?php // curl_exec($c); shell_exec('x')
        $s = "file_get_contents('https://x')";
        /* new PDO('sqlite:') */
        return strlen($s);
        '''
        code, _ = self.scan(php)
        self.assertEqual(code, 0)

    def test_network_and_process_effect_calls_are_rejected(self):
        for statement in (
            "curl_init('https://invalid.example');",
            "\\curl_exec($handle);",
            "fsockopen('invalid.example', 443);",
            "stream_socket_client('tls://invalid.example:443');",
            "shell_exec('whoami');",
            "system('whoami');",
            "proc_open('x', [], $pipes);",
            "file_get_contents('https://invalid.example');",
            "file_put_contents('/tmp/o', 'x');",
            "call_user_func('system', 'whoami');",
            "getenv('SECRET');",
            "mail('user@example.org','s','b');",
        ):
            with self.subTest(statement=statement):
                code, report = self.scan("<?php " + statement)
                self.assertEqual(code, 1)
                self.assertFalse(report["isolation_check_passed"])
                self.assertIn("external_or_effectful_call", report["violation_categories"])

    def test_external_constructors_are_rejected(self):
        for expression in ("new PDO('sqlite::memory:')", "new \\PDO('sqlite::memory:')",
                           "new Redis()", "new ReflectionFunction('system')"):
            with self.subTest(expression=expression):
                code, report = self.scan("<?php $x = " + expression + ";")
                self.assertEqual(code, 1)
                self.assertIn("external_constructor", report["violation_categories"])

    def test_runtime_globals_rejected(self):
        for key in ("$_SERVER", "$_ENV", "$_POST", "$_FILES", "$GLOBALS"):
            with self.subTest(key=key):
                code, report = self.scan("<?php $data = " + key + ";")
                self.assertEqual(code, 1)
                self.assertIn("global_runtime_input", report["violation_categories"])

    def test_dynamic_include_and_eval_rejected(self):
        for expression in ("eval('$x=2');", "require 'runtime.php';", "include_once 'x.php';"):
            with self.subTest(expression=expression):
                code, report = self.scan("<?php " + expression)
                self.assertEqual(code, 1)
                self.assertIn("dynamic_php_execution", report["violation_categories"])

    def test_variable_function_dispatch_denied(self):
        for statement in (
            "$fn = 'system'; $fn('echo synthetic');",
            "$fn = 'curl_exec'; $fn($handle);",
            "$cb = 'system'; ($cb)('echo synthetic');",
        ):
            with self.subTest(statement=statement):
                rc, report = self.scan("<?php " + statement)
                self.assertEqual(rc, 1)
                self.assertIn("dynamic_dispatch", report["violation_categories"])

    def test_dynamic_method_dispatch_denied(self):
        rc, report = self.scan("<?php $o->$method();")
        self.assertEqual(rc, 1)
        self.assertIn("dynamic_dispatch", report["violation_categories"])

    def test_dynamic_constructor_denied(self):
        for statement in ("$x = new $class();", "$x = new $factory;"):
            with self.subTest(statement=statement):
                rc, report = self.scan("<?php " + statement)
                self.assertEqual(rc, 1)
                self.assertIn("dynamic_constructor", report["violation_categories"])

    def test_backtick_shell_expression_denied(self):
        payload = "<?php $x = " + chr(96) + "echo synthetic" + chr(96) + ";"
        rc, report = self.scan(payload)
        self.assertEqual(rc, 1)
        self.assertIn("backtick_execution", report["violation_categories"])

    def test_imported_function_alias_denied(self):
        rc, report = self.scan("<?php use function system as helper; helper('x');")
        self.assertEqual(rc, 1)
        self.assertIn("function_import_alias", report["violation_categories"])

    def test_relative_namespaced_effect_call_denied(self):
        rc, report = self.scan(r"<?php namespace\system('echo synthetic');")
        self.assertEqual(rc, 1)
        self.assertIn("external_or_effectful_call", report["violation_categories"])

    def test_inert_backtick_and_variable_in_comments_and_strings(self):
        source = r'''<?php
        // $fn('unsafe'); use function system as bypass;
        $label = "words: $fn";
        /* new $class; namespace\system('synthetic') */
        return strlen($label);
        '''
        source = source.replace("words: $fn", "words: $fn " + chr(96) + "echo synthetic" + chr(96))
        rc, report = self.scan(source)
        self.assertEqual(rc, 0)
        self.assertTrue(report["isolation_check_passed"])

    def test_rejected_parse_error_does_not_disclose_source(self):
        code, report = self.scan("<?php invalid syntax !!! secret-token")
        self.assertEqual(code, 1)
        self.assertFalse(report["isolation_check_passed"])
        self.assertNotIn("secret-token", json.dumps(report))

    def test_no_php_is_not_success(self):
        proc = subprocess.run(["php", str(SCANNER), str(self.dir)], capture_output=True, text=True)
        self.assertEqual(proc.returncode, 1)
        self.assertEqual(json.loads(proc.stdout)["error_code"], "SOURCE_FILE_COUNT_INVALID")

    def test_symlinks_are_not_followed(self):
        source = self.dir / "legit.php"
        source.write_text("<?php return 1;", encoding="utf-8")
        (self.dir / "linked.php").symlink_to(source)
        proc = subprocess.run(["php", str(SCANNER), str(self.dir)], capture_output=True, text=True)
        self.assertEqual(proc.returncode, 1)
        self.assertEqual(json.loads(proc.stdout)["error_code"], "SYMLINK_NOT_ALLOWED")

    def test_unbounded_source_is_rejected(self):
        code, report = self.scan("<?php // " + "x" * (2*1024*1024))
        self.assertEqual(code, 1)
        self.assertEqual(report["error_code"], "SOURCE_FILE_UNREADABLE")


if __name__ == "__main__":
    unittest.main()
