<?php
// public/index.php  – main front controller

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();

require_once '../inc/db.php';
require_once '../inc/auth.php';
require_once '../inc/tenant.php';

$page   = $_GET['p'] ?? 'dashboard';
$public = ['login', 'do-login', 'logout'];

if (!in_array($page, $public) && !is_logged_in()) {
    header('Location: /login');
    exit;
}

include '../inc/header.php';
include "../pages/$page.php";
include '../inc/footer.php';
?>