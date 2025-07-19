<?php
// pages/files-list.php
// Lists all files for the current tenant, with search + tag filter.

require_once __DIR__ . '/../inc/storage.php';   // for drive_get_url() if you want preview links
require_once __DIR__ . '/../inc/acl.php';       // can_access_file()
// $mysqli and authentication are already loaded by public/index.php

$tid = $_SESSION['tid'];          // current tenant ID

// fetch tenant brand info
$tenant = $mysqli->query(
    "SELECT logo_path, color_primary, color_accent FROM tenants WHERE id = $tid"
)->fetch_assoc();
$aclMode = $tenant['acl_mode'] ?? 'all';
$primary = $tenant['color_primary'] ?: '#1e3a8a';
$accent  = $tenant['color_accent']  ?: '#facc15';
$logo    = $tenant['logo_path']     ?: '';

/* ──────────────────────────────────────────────
   1) Collect filter parameters
   ────────────────────────────────────────────── */
$q   = trim($_GET['q']   ?? '');   // full-text search
$tag = trim($_GET['tag'] ?? '');   // tag filter

$where  = "tenant_id = ?";
$params = ["i", $tid];             // first param type & value

if ($q !== '') {
    $where .= " AND (filename LIKE ? OR description LIKE ? OR tags LIKE ?)";
    $like   = "%$q%";
    $params[0] .= "sss";
    array_push($params, $like, $like, $like);
}
if ($tag !== '') {
    // strip spaces inside the tags column so “tag2” matches “ tag2”
    $where .= " AND FIND_IN_SET(?, REPLACE(tags,' ',''))";
    $params[0] .= "s";
    $params[] = $tag;
}

$grp = (int)($_GET['grp'] ?? 0);     // group ID filter
if ($grp) {
    // restrict by group membership
    $where .= " AND id IN (SELECT file_id FROM file_group WHERE group_id = ?)";
    $params[0] .= "i";
    $params[]    = $grp;
}

$groupsList = [];
if ($aclMode === 'group') {
    $gr = $mysqli->query("SELECT id,name FROM groups WHERE tenant_id = $tid ORDER BY name");
    while ($row = $gr->fetch_assoc()) { $groupsList[$row['id']] = $row['name']; }
}

/* ──────────────────────────────────────────────
   2) Query DB
   ────────────────────────────────────────────── */
$sql = "SELECT id, filename, description, tags, size, downloads,
               uploaded_at
        FROM files
        WHERE $where
        ORDER BY uploaded_at DESC";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param(...$params);
$stmt->execute();
$files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// remove rows the current user shouldn’t see (granular/group ACL)
$files = array_filter($files, function ($row) {
    return can_access_file($row);
});

$fileToGroups = [];
if ($aclMode === 'group' && $files) {
    $ids = array_column($files, 'id');
    $in  = implode(',', array_map('intval',$ids));
    $sqlG = "
      SELECT fg.file_id, GROUP_CONCAT(g.name ORDER BY g.name SEPARATOR ', ') AS gnames
      FROM   file_group fg
      JOIN   groups g ON g.id = fg.group_id
      WHERE  fg.file_id IN ($in)
      GROUP  BY fg.file_id";
    $resG = $mysqli->query($sqlG);
    while ($row = $resG->fetch_assoc()) {
        $fileToGroups[$row['file_id']] = $row['gnames'];
    }
}

/* tag list for dropdown */
$tagRows = $mysqli->query("SELECT DISTINCT tags FROM files WHERE tenant_id = $tid");
$allTags = [];
while ($r = $tagRows->fetch_row()) {
    foreach (explode(',', $r[0]) as $t) {
        $t = trim($t);
        if ($t) $allTags[] = $t;
    }
}
$allTags = array_unique($allTags);
sort($allTags);
?>

<style>
  .brand-bg   { background: <?= $primary ?>; }
  .brand-btn  { background: <?= $primary ?>; color: #fff; }
  .accent-btn { background: <?= $accent  ?>; color: #000; }
  .brand-text { color: <?= $primary ?>; }
</style>

<div class="max-w-5xl mx-auto mt-8">
  <h1 class="text-2xl font-semibold mb-4 brand-text flex items-center gap-3">
    <?php if ($logo): ?>
      <img src="<?= $logo ?>" class="h-8 w-auto" alt="logo">
    <?php endif; ?>
    Files
  </h1>

  <!-- Search + tag filter -->
  <form class="flex flex-wrap gap-2 mb-6" method="get">
    <input type="hidden" name="p" value="files-list">

    <input name="q"
           class="flex-1 border p-2 rounded"
           placeholder="Search name, description or tags"
           value="<?= htmlspecialchars($q) ?>">

    <select name="tag" class="border p-2 rounded">
      <option value="">— Tag —</option>
      <?php foreach ($allTags as $t): ?>
        <option value="<?= htmlspecialchars($t) ?>"
            <?= $t === $tag ? 'selected' : '' ?>>
          <?= htmlspecialchars($t) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <?php if ($aclMode === 'group'): ?>
      <select name="grp" class="border p-2 rounded">
          <option value="">— Group —</option>
          <?php foreach ($groupsList as $gid=>$gname): ?>
            <option value="<?= $gid ?>" <?= $gid==$grp?'selected':'' ?>>
              <?= htmlspecialchars($gname) ?>
            </option>
          <?php endforeach; ?>
      </select>
    <?php endif; ?>

    <button class="brand-btn px-4 rounded">Filter</button>
    <?php if ($q !== '' || $tag !== ''): ?>
      <a href="/?p=files-list"
         class="bg-gray-300 text-gray-800 px-4 py-2 rounded">View All</a>
    <?php endif; ?>
    <a href="/?p=files-upload"
       class="ml-auto inline-block accent-btn px-4 py-2 rounded text-center">Upload</a>
  </form>

  <?php if (!$files): ?>
    <p class="text-gray-600">No files found.</p>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="bg-gray-100 text-left">
          <th class="p-2">Name</th>
          <th class="p-2">Size</th>
          <th class="p-2">Tags</th>
          <?php if ($aclMode === 'group'): ?><th class="p-2">Groups</th><?php endif; ?>
          <th class="p-2">DLs</th>
          <th class="p-2">Added</th>
          <th class="p-2"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($files as $f): ?>
        <tr class="border-b">
          <td class="p-2">
            <?= htmlspecialchars($f['filename']) ?><br>
            <span class="text-xs text-gray-500">
              <?= htmlspecialchars($f['description']) ?>
            </span>
          </td>
          <td class="p-2"><?= round($f['size']/1024, 1) ?> KB</td>
          <td class="p-2">
            <?php foreach (explode(',', $f['tags']) as $tg):
                    $tg = trim($tg); if (!$tg) continue; ?>
              <a href="?p=files-list&amp;tag=<?= urlencode($tg) ?>"
                 class="inline-block bg-gray-200 px-2 py-0.5 rounded mr-1 text-xs">
                <?= htmlspecialchars($tg) ?>
              </a>
            <?php endforeach; ?>
          </td>
          <?php if ($aclMode === 'group'): ?>
            <td class="p-2 text-xs text-gray-700">
               <?= htmlspecialchars($fileToGroups[$f['id']] ?? '—') ?>
            </td>
          <?php endif; ?>
          <td class="p-2"><?= $f['downloads'] ?></td>
          <td class="p-2"><?= date('Y-m-d', strtotime($f['uploaded_at'])) ?></td>
          <td class="p-2">
            <a href="/download.php?id=<?= $f['id'] ?>"
               class="text-blue-600 underline">Download</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>