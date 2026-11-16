<?php
declare(strict_types=1);

use ImagineAI\MockBridge\MockSharedNonceLedger;

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'ImagineAI\\';
    if (str_starts_with($class, $prefix)) {
        require $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
function assertF233(bool $value): void {
    if (!$value) throw new RuntimeException('F233_DENIED');
}
try {
    $issuer = 'SYNTHETIC_AGENT_SERVICE';
    $nonce = 'SYNTHETIC_F233_NONCE_A';
    $now = 1794690010;
    $ledger = new MockSharedNonceLedger(2);
    // Simulate two call sites referencing the SAME local ledger object.
    $first = static fn(string $n) => $ledger->consumeOnce($issuer, $n, $now, $now + 20);
    $second = static fn(string $n) => $ledger->consumeOnce($issuer, $n, $now, $now + 20);
    assertF233($first($nonce));
    assertF233(!$second($nonce));
    assertF233(!$first($nonce));
    assertF233($ledger->consumedCount() === 1);

    // No tenant reset or eviction: a nonce is issuer-wide in this fixture.
    assertF233($second('SYNTHETIC_F233_NONCE_B'));
    assertF233(!$first('SYNTHETIC_F233_NONCE_C'));
    assertF233($ledger->consumedCount() === 2);

    // An outage fails closed and never clears prior records.
    $ledger->setAvailable(false);
    assertF233(!$first('SYNTHETIC_F233_NONCE_D'));
    $ledger->setAvailable(true);
    assertF233(!$second($nonce));
    assertF233($ledger->consumedCount() === 2);

    $invalid = [
        ['SYNTHETIC_AGENT_SERVICE','SYNTHETIC_NONCE',-1,20],
        ['SYNTHETIC_AGENT_SERVICE','SYNTHETIC_NONCE',0,0],
        ['SYNTHETIC_AGENT_SERVICE','SYNTHETIC_NONCE',0,31],
        ['SYNTHETIC_AGENT_SERVICE','SYNTHETIC_NONCE',0,4102444801],
        ['AGENT_SERVICE','SYNTHETIC_NONCE',0,20],
        ['SYNTHETIC_AGENT_SERVICE','NONCE',0,20],
        ['SYNTHETIC_AGENT_SERVICE','',0,20],
        ['SYNTHETIC_AGENT_SERVICE','SYNTHETIC_NONCE',4102444801,4102444802],
        ['SYNTHETIC_AGENT_SERVICE','SYNTHETIC_NONCE',20,19],
        ['SYNTHETIC_AGENT_SERVICE',str_repeat('X',120),0,20],
    ];
    foreach ($invalid as [$i,$n,$t,$expiry]) {
        $fresh = new MockSharedNonceLedger(1);
        assertF233(!$fresh->consumeOnce($i,$n,$t,$expiry));
        assertF233($fresh->consumedCount() === 0);
    }
    // Distinct instances with separate backing stores DO allow duplicate
    // nonce: explicit proof this fixture is NOT a distributed replay guard.
    $isolatedA = new MockSharedNonceLedger();
    $isolatedB = new MockSharedNonceLedger();
    assertF233($isolatedA->consumeOnce($issuer,$nonce,$now,$now+20));
    assertF233($isolatedB->consumeOnce($issuer,$nonce,$now,$now+20));
    echo "F233_SHARED_INSTANCE_PASS negatives=10 no_distributed_guarantee=true operational_decision=NO_GO\n";
} catch (Throwable) {
    fwrite(STDERR, "F233_SHARED_INSTANCE_DENIED operational_decision=NO_GO\n");
    exit(1);
}
