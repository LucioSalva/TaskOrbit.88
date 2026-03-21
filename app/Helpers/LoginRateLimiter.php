<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Database;

class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;
    private const CLEANUP_HOURS = 24;

    /**
     * Check whether the given identifier (username) or IP address is currently
     * blocked due to too many failed login attempts within the rolling window.
     */
    public static function isBlocked(string $identifier, string $ip): bool
    {
        $db = Database::getInstance();

        self::cleanup($db);

        $count = (int) $db->fetchOne(
            "SELECT COUNT(*) AS cnt
               FROM login_attempts
              WHERE (identifier = ? OR ip_address = ?)
                AND success = FALSE
                AND attempted_at > NOW() - INTERVAL '" . self::WINDOW_MINUTES . " minutes'",
            [$identifier, $ip]
        )['cnt'];

        return $count >= self::MAX_ATTEMPTS;
    }

    /**
     * Record a failed login attempt for the given identifier and IP.
     */
    public static function recordFailure(string $identifier, string $ip): void
    {
        $db = Database::getInstance();
        $db->execute(
            'INSERT INTO login_attempts (identifier, ip_address, success) VALUES (?, ?, FALSE)',
            [$identifier, $ip]
        );
    }

    /**
     * Record a successful login and clear all prior failed attempts for this
     * identifier so the window resets cleanly on next login cycle.
     */
    public static function recordSuccess(string $identifier, string $ip): void
    {
        $db = Database::getInstance();

        // Clear failed attempts for this identifier on success
        $db->execute(
            "DELETE FROM login_attempts
              WHERE identifier = ?
                AND success = FALSE",
            [$identifier]
        );

        // Insert the successful attempt for audit purposes
        $db->execute(
            'INSERT INTO login_attempts (identifier, ip_address, success) VALUES (?, ?, TRUE)',
            [$identifier, $ip]
        );
    }

    /**
     * How many minutes remain in the current block window for this identifier/IP.
     * Returns 0 if not currently blocked.
     */
    public static function minutesRemaining(string $identifier, string $ip): int
    {
        $db = Database::getInstance();

        $row = $db->fetchOne(
            "SELECT MIN(attempted_at) AS oldest
               FROM login_attempts
              WHERE (identifier = ? OR ip_address = ?)
                AND success = FALSE
                AND attempted_at > NOW() - INTERVAL '" . self::WINDOW_MINUTES . " minutes'",
            [$identifier, $ip]
        );

        if (!$row || !$row['oldest']) {
            return 0;
        }

        $oldest    = strtotime($row['oldest']);
        $unblockAt = $oldest + (self::WINDOW_MINUTES * 60);
        $remaining = $unblockAt - time();

        return $remaining > 0 ? (int) ceil($remaining / 60) : 0;
    }

    /**
     * Remove records older than CLEANUP_HOURS to keep the table lean.
     * Called automatically on every check so no cron dependency is needed.
     */
    private static function cleanup(Database $db): void
    {
        $db->execute(
            "DELETE FROM login_attempts
              WHERE attempted_at < NOW() - INTERVAL '" . self::CLEANUP_HOURS . " hours'"
        );
    }
}
