<?php
// inc/quota.php
require_once __DIR__ . '/db.php';

/**
 * Throws \RuntimeException when tenant is at or above 100 % quota.
 * Returns [$usedMb, $quotaMb, $percentage] when OK.
 */
function quota_check_or_throw(int $tenantId): array
{
    global $mysqli;
    $t = $mysqli->query(
        "SELECT storage_used_mb, storage_mb
         FROM   tenants
         WHERE  id = $tenantId"
    )->fetch_assoc();

    if (!$t || $t['storage_mb'] == 0) {
        // Should never happen – treat as unlimited
        return [0, 0, 0];
    }

    $pct = $t['storage_used_mb'] / $t['storage_mb'] * 100;

    if ($pct >= 100) {
        throw new RuntimeException(
            'Storage limit reached – please upgrade your plan to upload or share new files.'
        );
    }
    return [$t['storage_used_mb'], $t['storage_mb'], $pct];
}