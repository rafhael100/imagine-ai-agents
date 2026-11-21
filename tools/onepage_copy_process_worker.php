<?php
declare(strict_types=1);

// Isolated synthetic CI only. One stdin input, one stdout JSON output.
// Deliberately no network, environment-secret reads or input logs.
if (PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true' ||
    getenv('IMAGINE_ONEPAGE_PROCESS_LAB') !== '1') {
    fwrite(STDERR, "ONEPAGE_WORKER_DENIED\n");
    exit(1);
}
require_once dirname(__DIR__).'/src/Integration/OnepageCopyProcessWorker.php';

try {
    $input = stream_get_contents(STDIN, 2049);
    if (!is_string($input) || $input === '' || strlen($input) > 2048) {
        throw new RuntimeException();
    }
    $output = \Imagine\Agents\Integration\OnepageCopyProcessWorker::execute($input);
    fwrite(STDOUT, $output);
} catch (Throwable) {
    fwrite(STDERR, "ONEPAGE_WORKER_DENIED\n");
    exit(1);
}
