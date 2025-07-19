<?php
// pages/logout.php
require_once __DIR__ . '/../inc/auth.php';

logout();                 // destroy session
header('Location: /?p=login');   // send user to the login screen
exit;