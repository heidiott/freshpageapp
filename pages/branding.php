<?php
// pages/branding.php  – Manage tenant logo + brand colors

require_once __DIR__.'/../inc/storage.php';   // for drive_upload() if you prefer Wasabi
$tid  = $_SESSION['tid'];
$isAdmin = ($_SESSION['role'] === 'admin');    // adjust if you have super-admin

if (!$isAdmin) {
    http_response_code(403);
    exit('Admins only');
}

// ── fetch current tenant record
$tenant = $mysqli->query("SELECT * FROM tenants WHERE id=$tid")->fetch_assoc();

// ── handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $colorPrimary = trim($_POST['color_primary'] ?? '');
    $colorAccent  = trim($_POST['color_accent']  ?? '');

    // basic #RRGGBB validation
    foreach (['colorPrimary','colorAccent'] as $c) {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $$c)) {
            $errors[] = 'Colors must be in #RRGGBB format';
        }
    }

    // handle logo upload (optional)
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $tmp  = $_FILES['logo']['tmp_name'];
        $mime = mime_content_type($tmp);
        if (!in_array($mime,['image/png','image/jpeg','image/svg+xml'])) {
            $errors[] = 'Logo must be PNG, JPG, or SVG';
        } else {
            // save locally:  public/uploads/logos/<slug>.<ext>
            $ext  = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
            $dest = "/uploads/logos/{$tenant['slug']}.$ext";
            $full = __DIR__."/../public$dest";
            @mkdir(dirname($full), 0775, true);
            move_uploaded_file($tmp, $full);
            $mysqli->query(
               "UPDATE tenants SET logo_path='$dest' WHERE id=$tid"
            );
            $tenant['logo_path'] = $dest;
        }
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare(
          "UPDATE tenants SET color_primary=?, color_accent=? WHERE id=?"
        );
        $stmt->bind_param('ssi', $colorPrimary, $colorAccent, $tid);
        $stmt->execute();
        $tenant['color_primary'] = $colorPrimary;
        $tenant['color_accent']  = $colorAccent;
        $success = 'Brand settings updated!';
    }
}
?>

<div class="max-w-lg mx-auto mt-8">
  <h1 class="text-2xl font-semibold mb-6">Branding</h1>

  <?php if (!empty($success)): ?>
    <p class="bg-green-200 text-green-900 p-3 rounded mb-4"><?= $success ?></p>
  <?php endif; ?>
  <?php if (!empty($errors)): ?>
    <p class="bg-red-200 text-red-900 p-3 rounded mb-4">
      <?= implode('<br>', $errors) ?>
    </p>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="space-y-4">
    <!-- Logo -->
    <label class="block">
      <span class="block font-medium">Logo (PNG/JPG/SVG)</span>
      <?php if ($tenant['logo_path']): ?>
        <img src="<?= $tenant['logo_path'] ?>" class="h-16 my-2" alt="logo preview">
      <?php endif; ?>
      <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg"
             class="mt-1 block w-full border p-2 rounded">
    </label>

    <!-- Colors -->
    <div class="flex gap-4">
      <label class="flex-1">
        <span class="block font-medium">Primary Color</span>
        <input type="color" name="color_primary"
               value="<?= htmlspecialchars($tenant['color_primary'] ?? '#1e3a8a') ?>"
               class="w-full h-10 border rounded">
      </label>
      <label class="flex-1">
        <span class="block font-medium">Accent Color</span>
        <input type="color" name="color_accent"
               value="<?= htmlspecialchars($tenant['color_accent'] ?? '#facc15') ?>"
               class="w-full h-10 border rounded">
      </label>
    </div>

    <button class="bg-blue-600 text-white px-6 py-2 rounded">Save</button>
  </form>
</div>