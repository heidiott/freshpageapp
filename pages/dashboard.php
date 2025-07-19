<?php
// pages/dashboard.php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';

$uid  = $_SESSION['uid'];
$tid  = $_SESSION['tid'];

// fetch tenant branding
$tenant = $mysqli->query(
    "SELECT logo_path, color_primary, color_accent FROM tenants WHERE id = $tid"
)->fetch_assoc();
$primary = $tenant['color_primary'] ?: '#1e3a8a';
$accent  = $tenant['color_accent']  ?: '#facc15';
$logo    = $tenant['logo_path']     ?: '';

// simple stats to show something useful
$stats = $mysqli->query("
    SELECT
        (SELECT COUNT(*) FROM users WHERE tenant_id = $tid)      AS users,
        (SELECT COUNT(*) FROM files WHERE tenant_id = $tid)      AS files,
        (SELECT COALESCE(SUM(downloads),0) FROM files WHERE tenant_id = $tid) AS downloads
")->fetch_assoc();
?>

<style>
  .brand-bg   { background: <?= $primary ?>; }
  .brand-btn  { background: <?= $primary ?>; color:#fff; }
  .accent-btn { background: <?= $accent  ?>; color:#000; }
  .brand-text { color: <?= $primary ?>; }
</style>

<div class="max-w-4xl mx-auto mt-8 space-y-6">

    <h1 class="text-2xl font-semibold brand-text flex items-center gap-3">
      <?php if ($logo): ?>
        <img src="<?= $logo ?>" class="h-8 w-auto" alt="logo">
      <?php endif; ?>
      Welcome, <?= htmlspecialchars($_SESSION['email'] ?? 'User') ?>
    </h1>

    <div class="grid grid-cols-3 gap-4 text-center">
        <div class="bg-white/60 p-4 rounded shadow brand-bg/10">
            <p>Users</p>
            <p class="text-2xl font-bold"><?= $stats['users'] ?></p>
        </div>
        <div class="bg-white/60 p-4 rounded shadow brand-bg/10">
            <p>Files</p>
            <p class="text-2xl font-bold"><?= $stats['files'] ?></p>
        </div>
        <div class="bg-white/60 p-4 rounded shadow brand-bg/10">
            <p>Downloads</p>
            <p class="text-2xl font-bold"><?= $stats['downloads'] ?></p>
        </div>
    </div>

    <div class="space-x-4">
        <a class="accent-btn px-2 py-2 rounded" href="/?p=files-list">View Files</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <a class="accent-btn px-2 py-2 rounded" href="/?p=files-upload">Upload Files</a>
        <?php endif; ?>
        <a class="accent-btn px-2 py-2 rounded" href="/?p=stats">More Stats</a>
    </div>
</div>