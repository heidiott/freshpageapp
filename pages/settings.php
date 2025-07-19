

<?php
// pages/settings.php  — Tenant-level settings (ACL mode, branding overview)
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();                       // only tenant admins
require_once __DIR__ . '/../inc/acl.php';

$tid = $_SESSION['tid'];

// ── get branding (logo + colors)
$tenant = $mysqli->query(
  "SELECT acl_mode, logo_path, color_primary, color_accent
     FROM tenants WHERE id = $tid LIMIT 1"
)->fetch_assoc();

$mode        = $tenant['acl_mode'] ?? 'all';
$primary     = $tenant['color_primary'] ?: '#1e3a8a';
$accent      = $tenant['color_accent']  ?: '#facc15';
$logo        = $tenant['logo_path']     ?: '';
$successMsg  = '';

/* ──────────────────────────────────  handle POST  ────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newMode = ($_POST['mode'] === 'group') ? 'group' : 'all';
    if ($newMode !== $mode) {
        $stmt = $mysqli->prepare("UPDATE tenants SET acl_mode=? WHERE id=?");
        $stmt->bind_param('si', $newMode, $tid);
        $stmt->execute();
        $mode = $newMode;
        $_SESSION['tenant_acl_mode'] = $newMode;
        $successMsg = 'Security settings updated.';
    }
}
?>
<!-- brand colours -->
<style>
  .brand-text { color: <?= $primary ?>; }
  .brand-btn  { background: <?= $primary ?>; color:#fff; }
</style>

<div class="max-w-xl mx-auto mt-8 space-y-6">

  <h1 class="text-2xl font-semibold brand-text flex items-center gap-3">
    <?php if ($logo): ?>
      <img src="<?= $logo ?>" class="h-8 w-auto" alt="logo">
    <?php endif; ?>
    Tenant Settings
  </h1>

  <?php
  // Billing / Upgrade button (tenant admins + super admin)
  if (in_array($_SESSION['role'] ?? '', ['admin','super'])): ?>
      <div class="mt-4">
          <a href="/?p=billing"
            class="inline-block px-4 py-2 rounded font-semibold text-sm"
            style="background: <?= htmlspecialchars($accent) ?>; color:#000;">
              Manage Billing / Upgrade Plan
          </a>
      </div>
  <?php endif; ?>

  <?php if ($successMsg): ?>
    <p class="bg-green-200 text-green-900 p-3 rounded"><?= $successMsg ?></p>
  <?php endif; ?>

  <!-- ── Security mode form -->
  <form method="post" class="bg-white p-6 rounded shadow">
      <h2 class="text-lg font-medium mb-4">File Access Mode</h2>

      <label class="block mb-2">
        <input type="radio" name="mode" value="all"
               <?= $mode==='all'?'checked':''; ?>>
        <span class="ml-1">All users can access all files</span>
      </label>

      <label class="block">
        <input type="radio" name="mode" value="group"
               <?= $mode==='group'?'checked':''; ?>>
        <span class="ml-1">Control access by <strong>Group</strong></span>
      </label>

      <p class="text-sm text-gray-600 mt-3">
        Switch to <em>Group</em> mode to restrict certain files to specific groups of users.
      </p>

      <button class="brand-btn px-6 py-2 mt-6 rounded">Save</button>
  </form>

</div>