<?php
/**
 * Wasabi‑only storage helper.
 *
 * Keeps the same function names used elsewhere in the code base:
 *
 *   drive_upload($tmpPath, $originalName) : string
 *   drive_get_url($driveId)               : string
 *
 * The file is uploaded to a single Wasabi bucket and stored under a
 * key that includes the tenant slug so each company stays isolated:
 *
 *   <tenant‑slug>/<random>/<originalName>
 *
 * ENV variables required (add to .env or config.php):
 *
 *   WASABI_KEY      =
 *   WASABI_SECRET   =
 *   WASABI_REGION   = us-east-1
 *   WASABI_BUCKET   = freshpage-prod
 */

require_once __DIR__ . '/db.php';            // $mysqli
require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;

/**
 * Build / cache a Wasabi S3‑compatible client.
 */
function wasabi(): S3Client
{
    static $client;
    if ($client) {
        return $client;
    }

    return $client = new S3Client([
        'version'     => 'latest',
        'region'      => $_ENV['WASABI_REGION'] ?? 'us-east-1',
        'endpoint'    => 'https://s3.' . ($_ENV['WASABI_REGION'] ?? 'us-east-1') . '.wasabisys.com',
        'credentials' => [
            'key'    => $_ENV['WASABI_KEY'],
            'secret' => $_ENV['WASABI_SECRET'],
        ],
    ]);
}

/**
 * Upload the file and return the S3 object key we’ll store in MySQL.
 */
function drive_upload(string $tmpPath, string $originalName): string
{
    // Get the tenant slug for folder prefix
    $tid  = $_SESSION['tid'] ?? 0;
    $row  = $GLOBALS['mysqli']
                ->query("SELECT slug FROM tenants WHERE id = $tid")
                ->fetch_assoc();
    $slug = $row['slug'] ?? 'tenant';

    // Key:  <slug>/<rand>/<filename>
    $rand = bin2hex(random_bytes(8));
    $key  = "$slug/$rand/" . basename($originalName);

    // Upload private object
    wasabi()->putObject([
        'Bucket'     => $_ENV['WASABI_BUCKET'],
        'Key'        => $key,
        'SourceFile' => $tmpPath,
        'ACL'        => 'private',
    ]);

    return $key;      // store this as drive_id
}

/**
 * Generate a temporary (15‑min) signed URL for downloading.
 */
function drive_get_url(string $driveId): string
{
    $cmd = wasabi()->getCommand('GetObject', [
        'Bucket' => $_ENV['WASABI_BUCKET'],
        'Key'    => $driveId,
    ]);

    return wasabi()
        ->createPresignedRequest($cmd, '+15 minutes')
        ->getUri()
        ->__toString();
}