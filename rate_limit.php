<?php
// rate_limit.php
// Simple file-based rate limiter for small deployments.
// Provides per-key counters with a sliding window.

function rl_storage_dir(): string
{
    $d = __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'rate_limit';
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
    }
    return $d;
}

function rl_key_to_file(string $key): string
{
    $fn = sha1($key) . '.json';
    return rl_storage_dir() . DIRECTORY_SEPARATOR . $fn;
}

function rl_read(string $key): array
{
    $f = rl_key_to_file($key);
    if (!is_file($f)) return ['count' => 0, 'first' => 0];
    $s = @file_get_contents($f);
    if ($s === false) return ['count' => 0, 'first' => 0];
    $j = @json_decode($s, true);
    if (!is_array($j) || !isset($j['count']) || !isset($j['first'])) return ['count' => 0, 'first' => 0];
    return $j;
}

function rl_write(string $key, int $count, int $first): void
{
    $f = rl_key_to_file($key);
    $data = json_encode(['count' => $count, 'first' => $first]);
    @file_put_contents($f, $data, LOCK_EX);
}

function rl_clear(string $key): void
{
    $f = rl_key_to_file($key);
    if (is_file($f)) {
        @unlink($f);
    }
}

/**
 * Check whether the given key is allowed under the provided threshold and window.
 * Returns an associative array: ['allowed' => bool, 'count' => int, 'reset' => int]
 */
function rl_check(string $key, int $maxAttempts = 10, int $windowSeconds = 900): array
{
    $now = time();
    $rec = rl_read($key);
    $count = (int)($rec['count'] ?? 0);
    $first = (int)($rec['first'] ?? 0);

    if ($first === 0 || ($now - $first) > $windowSeconds) {
        // window expired — reset
        $count = 0;
        $first = $now;
        rl_write($key, $count, $first);
    }

    $allowed = $count < $maxAttempts;
    $reset = ($first + $windowSeconds) - $now;
    if ($reset < 0) $reset = 0;

    return ['allowed' => $allowed, 'count' => $count, 'reset' => $reset];
}

/**
 * Increment the counter for a key and return the new count.
 */
function rl_increment(string $key): int
{
    $now = time();
    $rec = rl_read($key);
    $count = (int)($rec['count'] ?? 0);
    $first = (int)($rec['first'] ?? 0);
    if ($first === 0) $first = $now;
    // if window expired, reset
    // default window used is 15min (900s) — we assume callers manage window alignment by calling rl_check first
    $window = 900;
    if (($now - $first) > $window) {
        $count = 0;
        $first = $now;
    }
    $count++;
    rl_write($key, $count, $first);
    return $count;
}

?>
