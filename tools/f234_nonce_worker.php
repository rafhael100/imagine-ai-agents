<?php
declare(strict_types=1);

/** F234 — CLI test harness; synthetic data and temporary local paths only. */
require dirname(__DIR__) . '/src/MockBridge/MockFileAtomicNonceLab.php';

use ImagineAI\MockBridge\MockFileAtomicNonceLab;

if ($argc < 3 || !in_array($argv[1], ['init', 'consume'], true)) {
    fwrite(STDERR, "F234_BAD_ARGS\n");
    exit(2);
}
try {
    $capacity = isset($argv[4]) ? (int) $argv[4] : 64;
    $ledger = new MockFileAtomicNonceLab($argv[2], $capacity);
    if ($argv[1] === 'init') {
        $okay = $ledger->initialize();
    } else {
        if ($argc !== 5) {
            exit(2);
        }
        $okay = $ledger->consumeOnce(
            'SYNTHETIC_AGENT_SERVICE', $argv[3], 1794690010, 1794690030
        );
    }
    echo $okay ? "RESERVED\n" : "DENIED\n";
} catch (\Throwable) {
    echo "DENIED\n";
}
