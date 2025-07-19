<?php
// quota warning banner
if (function_exists('is_logged_in') && is_logged_in()) {
    // DB helper (only once)
    require_once __DIR__ . '/db.php';
    $tid = $_SESSION['tid'] ?? null;
    if ($tid) {
        $t = $mysqli->query("SELECT storage_mb, storage_used_mb FROM tenants WHERE id = $tid")->fetch_assoc();
        if ($t && $t['storage_mb'] > 0) {
            $pc = ($t['storage_used_mb'] / $t['storage_mb']) * 100;
            if ($pc >= 100) {
                echo '<div class="bg-red-600 text-white text-center text-sm py-2">
                        Storage limit reached — uploads & new links disabled.
                        <a href="/?p=settings" class="underline ml-1">Upgrade plan →</a>
                      </div>';
            } elseif ($pc >= 90) {
                echo '<div class="bg-yellow-300 text-black text-center text-sm py-2">
                        You have used ' . round($pc, 1) . '% of your storage quota.
                        <a href="/?p=settings" class="underline ml-1">Upgrade plan</a>
                      </div>';
            }
        }
    }
}
?>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<nav class="bg-white shadow px-4 py-3">
    <a href="/" class="font-bold">FreshPage</a>
    <?php if (is_logged_in()): ?>
        <a class="ml-4" href="/?p=files-list">Files</a>
        <?php if ($_SESSION['role']==='admin'): ?>
            <a class="ml-2" href="/?p=files-upload">Upload</a>
            <a class="ml-2" href="/?p=branding">Branding</a>
            <a class="ml-2" href="/?p=settings">Settings</a>
        <?php endif; ?>
        <a class="ml-2" href="/?p=stats">Stats</a>
        <a class="ml-2" href="/?p=logout">Logout</a>
    <?php endif; ?>
</nav>