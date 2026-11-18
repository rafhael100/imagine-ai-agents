<?php
declare(strict_types=1);

namespace ImagineAI\Integration\Faisca;

/**
 * A bounded, pinned public editorial catalog. It never fetches a source or
 * treats a passage as an instruction, tool call or customer authorization.
 * UTF-8 passages have their own contract; legacy SYNTHETIC mocks are unchanged.
 */
final class PublicPassageCatalog
{
    public const VERSION = 'faisca-grounding-1';
    public const SHA256 = '205754a9b45e723560276e0f7145b05b1a1d3eb6d87c5dd9e006ce84b9aceed8';
    public const MAX_CATALOG_BYTES = 32768;
    private const LIFETIME = 30;
    private const SOURCE_COMMIT = '84b4c82d1a7ff77b4ee7212bc877d4639ceb809d';
    private const SOURCE_IDS = [
        'src/data/services.mjs' => 'comercial.services@209bd893:faisca-1',
        'src/data/commercial-faq.mjs' => 'comercial.faq@209bd893:faisca-1',
        'src/data/commercial-process.mjs' => 'comercial.process@209bd893:faisca-1',
    ];
    private const ACTION_IDS = [
        'sell', 'faq', 'after_sales', 'human', 'home', 'goal_presence',
        'goal_campaign', 'goal_sales', 'goal_ads', 'compare_sites', 'pricing',
        'process', 'support_status', 'support_issue', 'support_billing',
        'support_cancel', 'summary', 'materials_ready', 'materials_help',
        'channel_google', 'channel_meta', 'channel_compare', 'results', 'privacy', 'scope',
    ];
    private readonly array $catalog;
    private readonly array $passages;
    private readonly array $answers;
    private string $phase = 'idle';
    private bool $revoked = false;
    private array $disabledSources = [];
    private ?array $recipe = null;
    private ?array $selection = null;
    private ?int $selectedAt = null;
    private int $deliveries = 0;

    public function __construct(string $catalogJson)
    {
        if (strlen($catalogJson) > self::MAX_CATALOG_BYTES
            || !hash_equals(self::SHA256, hash('sha256', $catalogJson))) self::deny();
        try {
            $catalog = json_decode($catalogJson, true, 9, JSON_THROW_ON_ERROR);
        } catch (\Throwable) { self::deny(); }
        if (!self::closed($catalog, ['schema', 'version', 'scope', 'sourceSnapshot', 'passages', 'answers'])
            || $catalog['schema'] !== 'faisca.grounding.catalog.v1'
            || $catalog['version'] !== self::VERSION || $catalog['scope'] !== 'public_comercial'
            || !self::closed($catalog['sourceSnapshot'], ['repository', 'commit', 'files'])
            || $catalog['sourceSnapshot']['repository'] !== 'rafhael100/imagine-comercial'
            || $catalog['sourceSnapshot']['commit'] !== self::SOURCE_COMMIT
            || !self::closed($catalog['sourceSnapshot']['files'], array_keys(self::SOURCE_IDS))
            || !is_array($catalog['passages']) || !array_is_list($catalog['passages'])
            || count($catalog['passages']) < 1 || count($catalog['passages']) > 32
            || !is_array($catalog['answers']) || !array_is_list($catalog['answers'])
            || count($catalog['answers']) < 1 || count($catalog['answers']) > 32) self::deny();
        foreach ($catalog['sourceSnapshot']['files'] as $digest) if (!self::hex($digest)) self::deny();

        $passages = [];
        foreach ($catalog['passages'] as $passage) {
            if (!self::closed($passage, ['id', 'title', 'sourcePath', 'selector', 'text', 'digest', 'factKey', 'factValue'])
                || !self::id($passage['id']) || isset($passages[$passage['id']])
                || !self::text($passage['title'], 200) || !self::text($passage['selector'], 200)
                || !is_string($passage['sourcePath']) || !isset(self::SOURCE_IDS[$passage['sourcePath']])
                || !self::text($passage['text'], 512) || !self::hex($passage['digest'])
                || !hash_equals($passage['digest'], hash('sha256', $passage['text']))
                || !self::id($passage['factKey']) || !self::id($passage['factValue'])) self::deny();
            $passages[$passage['id']] = $passage;
        }
        $answers = [];
        foreach ($catalog['answers'] as $answer) {
            if (!self::closed($answer, ['id', 'passageIds', 'prefix', 'suffix', 'actionIds'])
                || !self::id($answer['id']) || isset($answers[$answer['id']])
                || !self::text($answer['prefix'], 512, true) || !self::text($answer['suffix'], 512, true)
                || !self::ids($answer['passageIds'], array_keys($passages), 3, 1)
                || !self::ids($answer['actionIds'], self::ACTION_IDS, 6, 0)) self::deny();
            $selected = array_map(static fn (string $id): array => $passages[$id], $answer['passageIds']);
            if (array_sum(array_map(static fn (array $passage): int => strlen($passage['text']), $selected)) > 1200
                || self::hasDeclaredConflict($selected)) self::deny();
            $answers[$answer['id']] = $answer;
        }
        $this->catalog = $catalog;
        $this->passages = $passages;
        $this->answers = $answers;
    }

    /** One exact answer binding per invocation; IDs cannot select another recipe. */
    public function select(string $answerId, array $passageIds, int $now): void
    {
        if ($this->phase !== 'idle' || $now < 0 || $now > 4102444700
            || !self::id($answerId) || !isset($this->answers[$answerId])) self::deny();
        $recipe = $this->answers[$answerId];
        if ($passageIds !== $recipe['passageIds']) self::deny();
        $this->recipe = $recipe;
        $this->selectedAt = $now;
        $this->phase = 'selected';
        $this->selection = $this->resolve();
    }

    /** Re-resolve original bytes and current logical grant/source state at delivery. */
    public function deliver(int $now): array
    {
        if ($this->phase !== 'selected' || $this->revoked || $this->selectedAt === null
            || $now < $this->selectedAt || $now >= $this->selectedAt + self::LIFETIME) self::deny();
        $current = $this->resolve();
        if ($current !== $this->selection) self::deny();
        ++$this->deliveries;
        return $current;
    }

    public function actionIds(): array
    {
        if ($this->phase !== 'selected' || $this->recipe === null) self::deny();
        return $this->recipe['actionIds'];
    }

    /** Broad R05 IDs label only its synthetic execution, never these new citations. */
    public function legacySourceIds(): array
    {
        if ($this->phase !== 'selected' || $this->recipe === null) self::deny();
        $ids = [];
        foreach ($this->recipe['passageIds'] as $id) {
            $source = self::SOURCE_IDS[$this->passages[$id]['sourcePath']];
            if (!in_array($source, $ids, true)) $ids[] = $source;
        }
        return $ids;
    }

    /** Trusted denial-only control; not exposed in request JSON. */
    public function revoke(): void { $this->revoked = true; }

    /** Trusted denial-only source control; disabling never removes a pinned entry. */
    public function disableSource(string $sourcePath): void
    {
        if (!isset(self::SOURCE_IDS[$sourcePath])) self::deny();
        $this->disabledSources[$sourcePath] = true;
    }

    public function close(): void { $this->phase = 'closed'; }

    public function inspect(): array
    {
        return ['phase' => $this->phase, 'deliveries' => $this->deliveries,
            'selectedPassages' => $this->recipe === null ? 0 : count($this->recipe['passageIds']),
            'revoked' => $this->revoked, 'disabledSources' => count($this->disabledSources)];
    }

    /** Finite declared facts only; this does not infer contradictions in prose. */
    public static function hasDeclaredConflict(array $passages): bool
    {
        if (!array_is_list($passages) || count($passages) > 3) self::deny();
        $facts = [];
        foreach ($passages as $passage) {
            if (!is_array($passage) || !isset($passage['factKey'], $passage['factValue'])
                || !self::id($passage['factKey']) || !self::id($passage['factValue'])) self::deny();
            if (isset($facts[$passage['factKey']]) && $facts[$passage['factKey']] !== $passage['factValue']) return true;
            $facts[$passage['factKey']] = $passage['factValue'];
        }
        return false;
    }

    private function resolve(): array
    {
        if ($this->recipe === null || $this->revoked) self::deny();
        $selected = [];
        $bytes = 0;
        foreach ($this->recipe['passageIds'] as $id) {
            $passage = $this->passages[$id];
            if (isset($this->disabledSources[$passage['sourcePath']])
                || !hash_equals($passage['digest'], hash('sha256', $passage['text']))) self::deny();
            $bytes += strlen($passage['text']);
            $selected[] = [...$passage, 'trust' => 'UNTRUSTED_DATA'];
        }
        if (count($selected) > 3 || $bytes > 1200 || self::hasDeclaredConflict($selected)) self::deny();
        $parts = [$this->recipe['prefix']];
        foreach ($selected as $passage) $parts[] = $passage['title'] . ":\n" . $passage['text'];
        $parts[] = $this->recipe['suffix'];
        $text = implode("\n\n", array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));
        if (!self::text($text, 3000)) self::deny();
        return [
            'catalogVersion' => self::VERSION, 'catalogDigest' => self::SHA256,
            'answerId' => $this->recipe['id'], 'passageIds' => $this->recipe['passageIds'],
            'passages' => $selected, 'text' => $text,
        ];
    }

    private static function closed(mixed $value, array $keys): bool
    {
        if (!is_array($value) || array_is_list($value) || count($value) !== count($keys)) return false;
        $actual = array_keys($value);
        sort($actual, SORT_STRING); sort($keys, SORT_STRING);
        return $actual === $keys;
    }

    private static function id(mixed $value): bool
    {
        return is_string($value) && strlen($value) >= 1 && strlen($value) <= 96
            && preg_match('/\A[a-z0-9][a-z0-9_.-]*\z/D', $value) === 1;
    }

    private static function hex(mixed $value): bool
    {
        return is_string($value) && strlen($value) === 64 && preg_match('/\A[a-f0-9]{64}\z/D', $value) === 1;
    }

    private static function text(mixed $value, int $limit, bool $empty = false): bool
    {
        return is_string($value) && ($empty || $value !== '') && strlen($value) <= $limit
            && preg_match('//u', $value) === 1
            && preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{2028}\x{2029}]/u', $value) === 0;
    }

    private static function ids(mixed $values, array $allowed, int $limit, int $minimum): bool
    {
        if (!is_array($values) || !array_is_list($values) || count($values) < $minimum || count($values) > $limit) return false;
        foreach ($values as $value) if (!is_string($value) || !in_array($value, $allowed, true)) return false;
        return count(array_unique($values, SORT_STRING)) === count($values);
    }

    private static function deny(): never { throw new \DomainException('grounding_denied'); }
}
