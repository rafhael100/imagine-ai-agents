<?php
declare(strict_types=1);

/**
 * Inspect an actual captured CRM HTTP body without printing its content.
 *
 * Usage: php tools/p65-crm-minimal-read-probe.php --status 200 --expected-ref UUID
 * The raw response body is read from stdin. Exit 0 means accepted format and
 * resource binding; exit 2 means denied. This program has no HTTP client and
 * cannot follow redirects, authenticate a session or create any permission.
 */
use ImagineAI\Integration\Crm\MinimalLeadProjection;

require_once dirname(__DIR__) . '/src/Integration/Crm/MinimalLeadProjection.php';

function p65Deny(): never
{
    fwrite(STDOUT, "AIA_P65_CRM_MINIMAL_READ_DENIED\n");
    exit(2);
}

if (PHP_SAPI !== 'cli' || count($argv) !== 5
    || $argv[1] !== '--status' || $argv[3] !== '--expected-ref'
    || preg_match('/\A[0-9]{3}\z/D', $argv[2]) !== 1) {
    p65Deny();
}

try {
    // Read one byte beyond the contract limit so oversized input is denied
    // without allocating or copying the complete untrusted response.
    $body = stream_get_contents(STDIN, 1025);
    if (!is_string($body)
        || MinimalLeadProjection::fromHttpResponse((int) $argv[2], $body, $argv[4]) === null) {
        p65Deny();
    }
    fwrite(STDOUT, "AIA_P65_CRM_MINIMAL_READ_ACCEPTED\n");
    exit(0);
} catch (\Throwable) {
    p65Deny();
}
