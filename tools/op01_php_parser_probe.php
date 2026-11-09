<?php
declare(strict_types=1);
/** Offline only. Consume JSON fixtures; emit booleans; NO CRM, HTTP, IAM or authorization. */
require dirname(__DIR__) . '/src/Domain/MockError.php';
require dirname(__DIR__) . '/src/MockBridge/MockCrmReadRequest.php';
use ImagineAI\MockBridge\MockCrmReadRequest;

$input = stream_get_contents(STDIN);
if (!is_string($input) || strlen($input) > 131072) {
    fwrite(STDERR, "PROBE_INPUT_DENIED\n"); exit(2);
}
try {
    $values = json_decode($input, false, 64, JSON_THROW_ON_ERROR);
    if (!is_array($values) || count($values) < 1 || count($values) > 80) {
        throw new RuntimeException('INVALID_MATRIX');
    }
    $accepted = [];
    foreach ($values as $candidate) {
        try {
            // Decode into stdClass first: nested {"0":"ref"} is NOT ["ref"].
            if (!$candidate instanceof stdClass) throw new RuntimeException('INVALID_CANDIDATE');
            new MockCrmReadRequest(get_object_vars($candidate));
            $accepted[] = true;
        } catch (Throwable) {
            $accepted[] = false;
        }
    }
    echo json_encode(['contract' => 'SYNTHETIC_PARSER_PROBE_V1',
                       'accepted' => $accepted], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    fwrite(STDERR, "PROBE_INPUT_DENIED\n"); exit(2);
}
