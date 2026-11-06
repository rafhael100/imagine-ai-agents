<?php
declare(strict_types=1);

namespace ImagineAI\Contracts;

use ImagineAI\Domain\Run;
/** A suggestion has no authority and no persistence side effects. */
interface AiSuggestionProvider { public function suggest(Run $run, array $fields): array; }
