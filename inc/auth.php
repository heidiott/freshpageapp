<?php
session_start();
require_once __DIR__ . '/db.php';

function is_logged_in()       { return isset($_SESSION['uid']); }

function require_admin() {
    if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        exit('403 – Admins only');
    }
}

/**
 * Persist user session
 *
 * @param int    $uid
 * @param string $role       (super|admin|user)
 * @param int    $tenantId
 * @param string $email
 */
function login($uid, $role, $tenantId, $email)
{
    global $mysqli;

    $row = $mysqli->query(
        "SELECT acl_mode FROM tenants WHERE id = $tenantId LIMIT 1"
    )->fetch_assoc();

    $_SESSION = [
        'uid'             => $uid,
        'role'            => $role,
        'tid'             => $tenantId,
        'email'           => $email,
        'tenant_acl_mode' => $row ? $row['acl_mode'] : 'all',
    ];
}

function logout() {
    session_destroy();
}