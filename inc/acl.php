<?php
function acl_default_group_id($tenantId) {
    static $cache = [];
    if (!isset($cache[$tenantId])) {
        global $mysqli;
        $row = $mysqli->query(
          "SELECT id FROM `groups`
            WHERE tenant_id=$tenantId AND is_default=1 LIMIT 1"
        )->fetch_assoc();
        $cache[$tenantId] = $row ? (int)$row['id'] : null;
    }
    return $cache[$tenantId];
}

function can_access_file($fileRowOrId) {
    global $mysqli;
    $uid = $_SESSION['uid'];
    $tid = $_SESSION['tid'];
    $mode = $_SESSION['tenant_acl_mode'] ?? 'all';

    // admin shortcut
    if ($_SESSION['role'] === 'admin') return true;

    // get file data
    if (is_array($fileRowOrId)) {
        $fid = $fileRowOrId['id'];
        $fileTenant = $fileRowOrId['tenant_id'] ?? $tid;
    } else {
        $fid = (int)$fileRowOrId;
        $fileTenant = $tid;
    }
    if ($fileTenant !== $tid) return false; // cross-tenant

    if ($mode === 'all') {
        // deny only if a restrictive group exists that this user is not in
        $sql = "SELECT 1
                  FROM file_group fg
                  JOIN `groups` g ON g.id = fg.group_id AND g.is_default = 0
                  LEFT JOIN group_user gu ON gu.group_id = g.id AND gu.user_id = $uid
                 WHERE fg.file_id = $fid AND gu.user_id IS NULL
                 LIMIT 1";
        return !$mysqli->query($sql)->fetch_row();
    }

    // group mode – allow if user is in any group attached to the file
    $sql = "SELECT 1
              FROM file_group fg
              JOIN group_user gu ON gu.group_id = fg.group_id
             WHERE fg.file_id = $fid AND gu.user_id = $uid
             LIMIT 1";
    return (bool)$mysqli->query($sql)->fetch_row();
}