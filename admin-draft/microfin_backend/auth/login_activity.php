<?php

if (!function_exists('mf_update_user_last_login')) {
    function mf_update_user_last_login(PDO $pdo, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $stmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ? AND deleted_at IS NULL');
            $stmt->execute([$userId]);
        } catch (Throwable $e) {
            error_log('Unable to update last_login for user ' . $userId . ': ' . $e->getMessage());
        }
    }
}

if (!function_exists('mf_last_login_exact_label')) {
    function mf_last_login_exact_label($value, string $fallback = 'Never'): string
    {
        $rawValue = trim((string) $value);
        if ($rawValue === '') {
            return $fallback;
        }

        try {
            return (new DateTimeImmutable($rawValue))->format('M d, Y g:i A');
        } catch (Throwable $e) {
            return $fallback;
        }
    }
}

if (!function_exists('mf_humanize_last_login')) {
    function mf_humanize_last_login($value, string $fallback = 'Never'): string
    {
        $rawValue = trim((string) $value);
        if ($rawValue === '') {
            return $fallback;
        }

        try {
            $loginAt = new DateTimeImmutable($rawValue);
            $now = new DateTimeImmutable('now');
        } catch (Throwable $e) {
            return $fallback;
        }

        $diffSeconds = $now->getTimestamp() - $loginAt->getTimestamp();
        if ($diffSeconds < 0) {
            return $loginAt->format('M d, Y g:i A');
        }

        if ($loginAt->format('Y-m-d') === $now->format('Y-m-d')) {
            if ($diffSeconds < 60) {
                return 'Just now';
            }

            if ($diffSeconds < 3600) {
                $minutes = (int) floor($diffSeconds / 60);
                return $minutes === 1 ? '1 min ago' : $minutes . ' mins ago';
            }

            $hours = (int) floor($diffSeconds / 3600);
            return $hours === 1 ? '1 hour ago' : $hours . ' hours ago';
        }

        $yesterday = $now->sub(new DateInterval('P1D'));
        if ($loginAt->format('Y-m-d') === $yesterday->format('Y-m-d')) {
            return 'Yesterday';
        }

        return $loginAt->format('M d, Y g:i A');
    }
}

if (!function_exists('mf_humanize_last_login_words')) {
    function mf_humanize_last_login_words($value, bool $isActiveNow = false, string $fallback = 'Never', $referenceNow = null): string
    {
        if ($isActiveNow) {
            return 'Active now';
        }

        $rawValue = trim((string) $value);
        if ($rawValue === '') {
            return $fallback;
        }

        try {
            $loginAt = new DateTimeImmutable($rawValue);
            $referenceNowValue = trim((string) $referenceNow);
            $now = $referenceNowValue !== '' ? new DateTimeImmutable($referenceNowValue) : new DateTimeImmutable('now');
        } catch (Throwable $e) {
            return $fallback;
        }

        $diffSeconds = $now->getTimestamp() - $loginAt->getTimestamp();
        if ($diffSeconds < 0) {
            return $fallback;
        }

        if ($diffSeconds < 3600) {
            $minutes = max(1, (int) floor($diffSeconds / 60));
            return $minutes === 1 ? '1 minute ago' : $minutes . ' minutes ago';
        }

        if ($loginAt->format('Y-m-d') === $now->format('Y-m-d')) {
            $hours = (int) floor($diffSeconds / 3600);
            return $hours === 1 ? '1 hour ago' : $hours . ' hours ago';
        }

        $yesterday = $now->sub(new DateInterval('P1D'));
        if ($loginAt->format('Y-m-d') === $yesterday->format('Y-m-d')) {
            return 'Yesterday';
        }

        $days = (int) floor($diffSeconds / 86400);
        if ($days < 7) {
            return $days === 1 ? '1 day ago' : $days . ' days ago';
        }

        if ($days < 30) {
            $weeks = (int) floor($days / 7);
            return $weeks === 1 ? '1 week ago' : $weeks . ' weeks ago';
        }

        if ($days < 365) {
            $months = (int) floor($days / 30);
            return $months === 1 ? '1 month ago' : $months . ' months ago';
        }

        $years = (int) floor($days / 365);
        return $years === 1 ? '1 year ago' : $years . ' years ago';
    }
}

if (!function_exists('mf_latest_activity_timestamp')) {
    function mf_latest_activity_timestamp($firstValue, $secondValue)
    {
        $candidates = [];

        foreach ([$firstValue, $secondValue] as $value) {
            $rawValue = trim((string)$value);
            if ($rawValue === '') {
                continue;
            }

            try {
                $parsed = new DateTimeImmutable($rawValue);
            } catch (Throwable $e) {
                continue;
            }

            $candidates[] = [
                'timestamp' => $parsed->getTimestamp(),
                'value' => $parsed->format('Y-m-d H:i:s'),
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, static function (array $left, array $right): int {
            return $right['timestamp'] <=> $left['timestamp'];
        });

        return $candidates[0]['value'];
    }
}
