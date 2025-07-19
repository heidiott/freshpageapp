<?php
/**
 * inc/storage.php
 *
 * Wasabi‑only storage driver.  Exposes:
 *   drive_upload($tmpPath,$originalName) : string  // returns object key
 *   drive_get_url($driveId)              : string  // 15‑minute signed URL
 *
 * Required env vars (via Dotenv or SetEnv):
 *   WASABI_KEY, WASABI_SECRET, WASABI_REGION, WASABI_BUCKET
 */

require_once __DIR__ . '/../vendor/autoload.php';
use Aws\S3\S3Client;

/** create/cache S3‑compatible client */
function wasabi(): S3Client
{
    static $c;
    if ($c) return $c;

    $region = $_ENV['WASABI_REGION']  ?? $_SERVER['WASABI_REGION']  ?? 'us-east-1';
    $key    = $_ENV['WASABI_KEY']     ?? $_SERVER['WASABI_KEY']     ?? null;
    $secret = $_ENV['WASABI_SECRET']  ?? $_SERVER['WASABI_SECRET']  ?? null;
    $bucket = $_ENV['WASABI_BUCKET']  ?? $_SERVER['WASABI_BUCKET']  ?? null;

    if (!$key || !$secret || !$bucket) {
        throw new RuntimeException(
            'Wasabi credentials missing; set WASABI_KEY/SECRET/REGION/BUCKET.'
        );
    }
    $GLOBALS['wasabi_bucket'] = $bucket;

    return $c = new S3Client([
        'version'     => 'latest',
        'region'      => $region,
        'endpoint'    => "https://s3.$region.wasabisys.com",
        'credentials' => [ 'key' => $key, 'secret' => $secret ],
    ]);
}

/** upload file and return object key */
function drive_upload(string $tmp, string $orig): string
{
    $slug = 'tenant';
    if (!empty($_SESSION['tid']) && !empty($GLOBALS['mysqli'])) {
        $tid  = (int)$_SESSION['tid'];
        $row  = $GLOBALS['mysqli']
                ->query("SELECT slug FROM tenants WHERE id=$tid")
                ->fetch_assoc();
        $slug = $row['slug'] ?? $slug;
    }

    $key = "$slug/" . bin2hex(random_bytes(8)) . '/' . basename($orig);

    wasabi()->putObject([
        'Bucket'     => $GLOBALS['wasabi_bucket'],
        'Key'        => $key,
        'SourceFile' => $tmp,
        'ACL'        => 'private',
    ]);
    return $key;
}

/** generate 15‑minute presigned download URL */
function drive_get_url(string $id): string
{
    $cmd = wasabi()->getCommand('GetObject', [
        'Bucket' => $GLOBALS['wasabi_bucket'],
        'Key'    => $id,
    ]);
    return wasabi()
            ->createPresignedRequest($cmd,'+15 minutes')
            ->getUri()->__toString();
}