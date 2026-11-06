<?php
declare(strict_types=1);

namespace ImagineAI\Domain;

/** Local mock errors; no CRM authentication or authorization is implemented. */
final class MockError extends \DomainException
{
    public static function fail(string $code): never { throw new self($code); }
}
