<?php 
$mysqli = new mysqli('127.0.0.1','fpadmin','1967@Raina!','fpapp',8889);
if ($mysqli->connect_error) { die($mysqli->connect_error); }
$mysqli->set_charset('utf8mb4');