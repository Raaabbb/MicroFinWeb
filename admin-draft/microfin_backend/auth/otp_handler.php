<?php
/**
 * PHP OTP Handler
 * Centralized logic for generating, persisting, verifying, and throttling OTPs.
 * Uses a local JSON file to persist state without touching the main SQL database,
 * making it lightweight and easy to clean up.
 */

define('MF_OTP_CACHE_FILE', __DIR__ . '/temp_cache/otp_throttle.json');
define('MF_OTP_COOLDOWN_SECONDS', 300); // 5 minutes between sends
define('MF_OTP_EXPIRY_SECONDS', 900); // 15 minutes valid OTP

/**
 * Ensures the cache directory and file exist.
 */
function mf_otp_init_cache() {
    $dir = dirname(MF_OTP_CACHE_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!file_exists(MF_OTP_CACHE_FILE)) {
        @file_put_contents(MF_OTP_CACHE_FILE, json_encode([]));
    }
}

/**
 * Reads the cache, cleaning up expired/old throttles to keep the file small.
 */
function mf_otp_read_cache(): array {
    mf_otp_init_cache();
    $data = @file_get_contents(MF_OTP_CACHE_FILE);
    $cache = json_decode((string)$data, true);
    if (!is_array($cache)) {
        $cache = [];
    }

    $now = time();
    $cleaned = [];
    foreach ($cache as $email => $record) {
        $next_send = (int)($record['next_send_allowed_at'] ?? 0);
        $expires = (int)($record['expires_at'] ?? 0);
        
        // Keep the record if either it's still blocking a send, or the OTP is still valid.
        if ($next_send > $now || $expires > $now) {
            $cleaned[$email] = $record;
        }
    }

    return $cleaned;
}

/**
 * Writes the cache. Uses an exclusive lock to prevent race conditions.
 */
function mf_otp_write_cache(array $cache) {
    mf_otp_init_cache();
    $fp = fopen(MF_OTP_CACHE_FILE, 'c+');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, json_encode($cache, JSON_PRETTY_PRINT));
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}

/**
 * Checks if we are allowed to send a new OTP to this email.
 * Returns true if allowed, false if currently throttled.
 */
function mf_otp_can_send(string $email): bool {
    $cache = mf_otp_read_cache();
    $email = strtolower(trim($email));

    if (isset($cache[$email])) {
        $now = time();
        $next_send = (int)($cache[$email]['next_send_allowed_at'] ?? 0);
        if ($now < $next_send) {
            return false;
        }
    }
    return true;
}

/**
 * Gets the remaining throttling seconds before another OTP can be sent to this email.
 */
function mf_otp_get_remaining_seconds(string $email): int {
    $cache = mf_otp_read_cache();
    $email = strtolower(trim($email));

    if (isset($cache[$email])) {
        $now = time();
        $next_send = (int)($cache[$email]['next_send_allowed_at'] ?? 0);
        if ($now < $next_send) {
            return $next_send - $now;
        }
    }
    return 0;
}

/**
 * Generates and saves a new OTP for the given email, applying the cooldown.
 * Returns the generated 6-digit OTP string.
 */
function mf_otp_save(string $email): string {
    $cache = mf_otp_read_cache();
    $email = strtolower(trim($email));
    
    // Generate 6 digit crypto-secure random integer
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $now = time();

    $cache[$email] = [
        'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
        'created_at' => $now,
        'expires_at' => $now + MF_OTP_EXPIRY_SECONDS,
        'next_send_allowed_at' => $now + MF_OTP_COOLDOWN_SECONDS
    ];

    mf_otp_write_cache($cache);

    return $otp;
}

/**
 * Verifies the provided OTP for the given email.
 * Returns true if valid, false otherwise.
 * If valid, the OTP is cleared to prevent reuse (but throttling is preserved).
 */
function mf_otp_verify(string $email, string $otp_input): bool {
    $cache = mf_otp_read_cache();
    $email = strtolower(trim($email));
    $otp_input = trim($otp_input);
    
    if (!isset($cache[$email])) {
        return false;
    }

    $record = $cache[$email];
    $now = time();
    $expires = (int)($record['expires_at'] ?? 0);

    if ($now > $expires) {
        return false; // OTP expired
    }

    if (password_verify($otp_input, (string)($record['otp_hash'] ?? ''))) {
        // Success! Clear the OTP so it can't be reused, but KEEP the throttle active.
        $cache[$email]['otp_hash'] = null; // nullifies the hash
        $cache[$email]['expires_at'] = 0; // nullifies expiration
        mf_otp_write_cache($cache);
        return true;
    }

    return false;
}
