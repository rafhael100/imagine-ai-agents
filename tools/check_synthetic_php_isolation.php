<?php
declare(strict_types=1);

/**
 * Static offline guard for development-only PHP source. NOT a PHP sandbox,
 * a complete static analyzer, or production approval. Never executes source.
 */
final class SyntheticIsolationCheck
{
    private const CALLS = [
        'curl_init', 'curl_exec', 'curl_multi_init', 'curl_multi_exec', 'curl_setopt',
        'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_connect',
        'socket_create', 'file_get_contents', 'file_put_contents', 'fopen',
        'fwrite', 'fputs', 'copy', 'rename', 'unlink', 'move_uploaded_file',
        'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen',
        'pcntl_exec', 'call_user_func', 'call_user_func_array',
        'mysqli_connect', 'mysqli_real_connect', 'pg_connect', 'pg_pconnect',
        'getenv', 'putenv', 'mail', 'header',
    ];
    private const CONSTRUCTORS = [
        'pdo', 'mysqli', 'redis', 'memcached', 'soapclient', 'reflectionfunction',
        'reflectionclass', 'curlhandle', 'socket', 'amqpconnection',
    ];
    private const SUPERGLOBALS = [
        '$GLOBALS', '$_SERVER', '$_ENV', '$_REQUEST', '$_POST', '$_GET',
        '$_COOKIE', '$_SESSION', '$_FILES',
    ];

    private static function significant(array $tokens, int $from, int $step): int
    {
        for ($i = $from; isset($tokens[$i]); $i += $step) {
            $tok = $tokens[$i];
            if (!is_array($tok) || !in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return $i;
            }
        }
        return -1;
    }

    public static function audit(string $content): array
    {
        $tokens = token_get_all($content, TOKEN_PARSE);
        $bad = [];
        foreach ($tokens as $i => $tok) {
            // PHP shell backticks appear as single-character tokens.
            if ($tok === chr(96)) {
                $bad[] = 'backtick_execution';
                continue;
            }
            if (!is_array($tok)) continue;
            [$type, $value] = $tok;
            if (in_array($type, [T_EVAL, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)) {
                $bad[] = 'dynamic_php_execution';
                continue;
            }
            if ($type === T_USE) {
                $lookAhead = self::significant($tokens, $i + 1, 1);
                if ($lookAhead >= 0 && is_array($tokens[$lookAhead])
                    && $tokens[$lookAhead][0] === T_FUNCTION) {
                    // Function aliases can hide the original blocked callable.
                    $bad[] = 'function_import_alias';
                }
            }
            if ($type === T_VARIABLE) {
                if (in_array($value, self::SUPERGLOBALS, true)) {
                    $bad[] = 'global_runtime_input';
                }
                $next = self::significant($tokens, $i + 1, 1);
                $prior = self::significant($tokens, $i - 1, -1);
                $priorToken = $prior >= 0 ? $tokens[$prior] : null;
                $priorType = is_array($priorToken) ? $priorToken[0] : null;
                if ($priorType === T_NEW) {
                    $bad[] = 'dynamic_constructor';
                }
                if ($next >= 0 && $tokens[$next] === '(') {
                    // $fn(...) and $object->$method(...), regardless of contents.
                    $bad[] = 'dynamic_dispatch';
                } elseif ($prior >= 0 && $priorToken === '('
                    && $next >= 0 && $tokens[$next] === ')') {
                    // Parenthesized callable: ($fn)(...).
                    $after = self::significant($tokens, $next + 1, 1);
                    if ($after >= 0 && $tokens[$after] === '(') {
                        $bad[] = 'dynamic_dispatch';
                    }
                }
                continue;
            }
            if (!in_array($type, [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_NAME_RELATIVE], true)) continue;
            $name = strtolower(substr(strrchr('\\' . $value, '\\'), 1));
            $next = self::significant($tokens, $i + 1, 1);
            $prior = self::significant($tokens, $i - 1, -1);
            $priorToken = $prior >= 0 ? $tokens[$prior] : null;
            $priorType = is_array($priorToken) ? $priorToken[0] : null;
            if ($priorType === T_NEW && in_array($name, self::CONSTRUCTORS, true)) {
                $bad[] = 'external_constructor';
            }
            if ($next >= 0 && $tokens[$next] === '('
                && !in_array($priorType, [T_FUNCTION, T_FN, T_NEW, T_OBJECT_OPERATOR,
                                           T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)
                && in_array($name, self::CALLS, true)) {
                $bad[] = 'external_or_effectful_call';
            }
        }
        return array_values(array_unique($bad));
    }
}

$root = $argv[1] ?? dirname(__DIR__) . '/src';
$report = [
    'report' => 'AIA_FT01_SYNTHETIC_ISOLATION_V1',
    'source_only' => true,
    'operational_decision' => 'NO_GO',
    'authorizes_credentials' => false,
    'authorizes_deploy' => false,
    'network_calls' => 0,
    'files_scanned' => 0,
    'isolation_check_passed' => false,
];
$violations = [];
try {
    if (!is_dir($root)) throw new RuntimeException('SOURCE_DIRECTORY_MISSING');
    $paths = [];
    $dir = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        if ($item->isLink()) throw new RuntimeException('SYMLINK_NOT_ALLOWED');
        if ($item->isFile() && strtolower($item->getExtension()) === 'php') {
            $paths[] = $item->getPathname();
        }
    }
    sort($paths, SORT_STRING);
    if (count($paths) < 1 || count($paths) > 256) {
        throw new RuntimeException('SOURCE_FILE_COUNT_INVALID');
    }
    foreach ($paths as $path) {
        $content = file_get_contents($path);  // Scanner only; src is NOT executed.
        if (!is_string($content) || strlen($content) > 2 * 1024 * 1024) {
            throw new RuntimeException('SOURCE_FILE_UNREADABLE');
        }
        $report['files_scanned']++;
        foreach (SyntheticIsolationCheck::audit($content) as $violation) {
            $violations[] = $violation;  // No source content or paths in reports.
        }
    }
    $report['violation_count'] = count($violations);
    $report['violation_categories'] = array_values(array_unique($violations));
    $report['isolation_check_passed'] = $violations === [];
    $code = $violations === [] ? 0 : 1;
} catch (Throwable $error) {
    $report['error_code'] = $error instanceof ParseError ? 'SOURCE_PARSE_INVALID' : $error->getMessage();
    $code = 1;
}
echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
exit($code);
