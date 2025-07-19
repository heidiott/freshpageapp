<?php
require_once __DIR__ . '/../inc/db.php';       // DB connection
require_once __DIR__ . '/../inc/auth.php';     // auth helpers
require_admin();                               // gate to admins
require_once __DIR__ . '/../inc/drive.php';    // local/cloud storage helpers
require_once __DIR__ . '/../inc/acl.php';     // default group helpers
$tid = $_SESSION['tid'];
$tenant = $mysqli->query(
    "SELECT logo_path, color_primary, color_accent, acl_mode FROM tenants WHERE id = $tid"
)->fetch_assoc();
$aclMode = $tenant['acl_mode'] ?? 'all';   // cache once
$primary = $tenant['color_primary'] ?: '#1e3a8a';
$accent  = $tenant['color_accent']  ?: '#facc15';
$logo    = $tenant['logo_path']     ?: '';

// fetch groups if tenant is in group ACL mode
$groups = [];
if ($aclMode === 'group') {
    $res = $mysqli->query("SELECT id,name FROM groups WHERE tenant_id = $tid ORDER BY name");
    while ($row = $res->fetch_assoc()) {
        $groups[] = $row;
    }
}

require_once __DIR__ . '/../inc/quota.php';

try {
    quota_check_or_throw($_SESSION['tid']);
} catch (RuntimeException $e) {
    echo '<div class="bg-red-600 text-white p-3 rounded">' .
         htmlspecialchars($e->getMessage()) .
         '</div>';
    return;                         // bail out – form won’t render
}
?>
<!-- inline brand styles -->
<style>
  .brand-text  { color: <?= $primary ?>; }
  .accent-btn  { background: <?= $accent ?>; color:#000; }
</style>
<div class="max-w-xl mx-auto">
    <h1 class="text-2xl font-semibold brand-text flex items-center gap-3 my-4">
    <?php if ($logo): ?>
        <img src="<?= $logo ?>" class="h-8 w-auto" alt="logo">
    <?php endif; ?>
    Upload Files
    </h1>
    <?php
    ?>
    <form id="up" class="p-6 bg-white rounded shadow" enctype="multipart/form-data" method="post">
        <input type="file" name="files[]" multiple class="border p-2 w-full">
        <input type="text" name="tags"  placeholder="tags (comma-separated)"  class="border p-2 w-full mt-2">
        <textarea name="desc" placeholder="description" class="border p-2 w-full mt-2"></textarea>
        <?php if ($aclMode === 'group' && $groups): ?>
            <fieldset class="border p-2 w-full mt-2">
                <legend class="text-sm text-gray-600">Assign to group(s)</legend>
                <div class="flex flex-wrap gap-3 mt-1">
                    <?php foreach ($groups as $g): ?>
                        <label class="inline-flex items-center gap-1 text-sm">
                            <input type="checkbox" name="groups[]" value="<?= $g['id'] ?>">
                            <?= htmlspecialchars($g['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endif; ?>
        <button class="accent-btn px-4 py-2 mt-3 rounded">Upload</button>
    </form>
</div>
<?php
if ($_SERVER['REQUEST_METHOD']==='POST') {
    foreach ($_FILES['files']['tmp_name'] as $idx=>$tmp) {
        $name = $_FILES['files']['name'][$idx];
        // 1. upload to Wasabi and get the object key
        $driveId = drive_upload($tmp,$name);
        // 2. insert row
        $stmt = $mysqli->prepare(
          "INSERT INTO files (tenant_id,drive_id,filename,mime_type,size,tags,description)
           VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('isssiss',
            $_SESSION['tid'],$driveId,$name,
            $_FILES['files']['type'][$idx],
            $_FILES['files']['size'][$idx],
            $_POST['tags'], $_POST['desc']);
        $stmt->execute();
        $fid = $stmt->insert_id;     // new file ID for mapping
        // 3. group mapping
        if ($aclMode === 'group') {
            // If tenant checked boxes use those; otherwise fall back to Everyone
            $chosen = $_POST['groups'] ?? [];
            if (!$chosen) {
                $chosen = [ acl_default_group_id($_SESSION['tid']) ];
            }
            foreach ($chosen as $gid) {
                $gid = (int)$gid;
                $mysqli->query(
                    "INSERT IGNORE INTO file_group (file_id, group_id) VALUES ($fid, $gid)"
                );
            }
        } else {
            // ACL mode 'all' – always link to Everyone
            $gid = acl_default_group_id($_SESSION['tid']);
            if ($gid) {
                $mysqli->query(
                    "INSERT IGNORE INTO file_group (file_id, group_id) VALUES ($fid, $gid)"
                );
            }
        }
    }
    echo "<p class='text-green-700 mt-4'>Uploaded!</p>";
}