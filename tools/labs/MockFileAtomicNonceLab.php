<?php
declare(strict_types=1);

namespace ImagineAI\MockBridge;

/**
 * F234 — TEMPORARY LOCAL FILE laboratory, never an operational nonce store.
 * Proves serialized reservation across PHP processes on ONE local host.
 * No production use: no cluster consensus, no crash-durable journal, no IAM.
 */
final class MockFileAtomicNonceLab
{
    public function __construct(
        private readonly string $path,
        private readonly int $capacity = 64
    ) {
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        if ($capacity < 1 || $capacity > 256
            || !str_starts_with($path, $dir . DIRECTORY_SEPARATOR)
            || preg_match('/\Aaia-f234-[a-f0-9]{16,32}\.json\z/D', basename($path)) !== 1
            || dirname($path) !== $dir) {
            throw new \InvalidArgumentException('F234 unsafe laboratory configuration');
        }
    }

    /** Initialization is explicit and must finish BEFORE worker admission. */
    public function initialize(): bool
    {
        if (file_exists($this->path) || is_link($this->path)) {
            return false;
        }
        $h = @fopen($this->path, 'x');
        if ($h === false) {
            return false;
        }
        $initial = '{"v":1,"used":{}}';
        $written = fwrite($h, $initial);
        $ok = $written === strlen($initial) && fflush($h);
        fclose($h);
        return $ok;
    }

    /** A true result means an offline test reservation, NOT CRM authorization. */
    public function consumeOnce(string $issuer, string $nonce, int $now, int $expiry): bool
    {
        if (!self::synthetic($issuer) || !self::synthetic($nonce)
            || $now < 0 || $expiry <= $now || $expiry > 4102444800
            || $expiry - $now > 30 || is_link($this->path)
            || !is_file($this->path)) {
            return false;
        }
        $h = @fopen($this->path, 'r+');
        if ($h === false) {
            return false;
        }
        $locked = false;
        try {
            if (!flock($h, LOCK_EX)) {
                return false;
            }
            $locked = true;
            rewind($h);
            $body = stream_get_contents($h, 32769);
            if (!is_string($body) || $body === '' || strlen($body) > 32768) {
                return false;
            }
            $record = json_decode($body, true, 4, JSON_THROW_ON_ERROR);
            if (!is_array($record) || array_keys($record) !== ['v', 'used']
                || $record['v'] !== 1 || !is_array($record['used'])
                || count($record['used']) > $this->capacity) {
                return false;
            }
            foreach ($record['used'] as $digest => $deadline) {
                if (!is_string($digest) || !preg_match('/\A[a-f0-9]{64}\z/D', $digest)
                    || !is_int($deadline) || $deadline < 0) {
                    return false;
                }
            }
            $digest = hash('sha256', strlen($issuer) . ':' . $issuer . '|'
                . strlen($nonce) . ':' . $nonce);
            if (array_key_exists($digest, $record['used'])
                || count($record['used']) >= $this->capacity) {
                return false;
            }
            // No TTL eviction, for conservative replay protection in this lab.
            $record['used'][$digest] = $expiry;
            $encoded = json_encode($record, JSON_THROW_ON_ERROR);
            if (!is_string($encoded) || strlen($encoded) > 32768
                || !rewind($h) || !ftruncate($h, 0)) {
                return false;
            }
            $offset = 0;
            while ($offset < strlen($encoded)) {
                $wrote = fwrite($h, substr($encoded, $offset));
                if (!is_int($wrote) || $wrote < 1) {
                    return false;
                }
                $offset += $wrote;
            }
            return fflush($h);
        } catch (\Throwable) {
            return false; // Corrupt or partial storage is denied, never reset.
        } finally {
            if ($locked) {
                flock($h, LOCK_UN);
            }
            fclose($h);
        }
    }

    private static function synthetic(string $value): bool
    {
        return strlen($value) <= 90
            && preg_match('/\ASYNTHETIC_[A-Z0-9_]{1,80}\z/D', $value) === 1;
    }
}
