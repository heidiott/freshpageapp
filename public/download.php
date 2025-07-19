<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

// public/download.php
//
// Handles all file downloads:
//
//   https://freshpageapp:8890/download.php?id=123
//
// 1. Confirms user is logged in.
// 2. Ensures the file belongs to the same tenant.
// 3. Increments the downloads counter.
// 4. Redirects to the cloud URL (Google Drive / Dropbox)
//    OR streams the local file from /public/uploads/.
//
// ------------------------------------------------------------------

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/acl.php';      // access‑control helpers

if (!is_logged_in()) {
    header('Location: /login');
    exit;
}

$fileId = (int)($_GET['id'] ?? 0);
if ($fileId <= 0) {
    http_response_code(400);
    exit('Bad request.');
}

// ─────────────────────────────────────────────────────────────
// Look up the file
// ─────────────────────────────────────────────────────────────
$stmt = $mysqli->prepare(
    "SELECT drive_id, filename, mime_type, size, tenant_id
       FROM files
      WHERE id = ?"
);
$stmt->bind_param('i', $fileId);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
if (!$file) {
    http_response_code(404);
    exit('File not found.');
}
if (!can_access_file($file)) {
    http_response_code(403);
    exit('Access denied.');
}

// ─────────────────────────────────────────────────────────────
// Update download counter (fire-and-forget)
// ─────────────────────────────────────────────────────────────
$mysqli->query("UPDATE files SET downloads = downloads + 1 WHERE id = $fileId");

// ─────────────────────────────────────────────────────────────
// Serve or redirect
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../inc/drive.php';      // has drive_get_url()

if ($file['drive_id']) {
    // Cloud-stored — redirect to a time-limited link
    $url = drive_get_url($file['drive_id']);     // implement in inc/drive.php
    header('Location: ' . $url, true, 302);
    exit;
}

// Local fallback (file was stored in /public/uploads/)
$localPath = __DIR__ . '/uploads/' . $file['drive_id']; // or another column
if (!is_readable($localPath)) {
    http_response_code(404);
    exit('File missing from server.');
}

header('Content-Type: ' . $file['mime_type']);
header('Content-Length: ' . $file['size']);
header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
readfile($localPath);
exit;