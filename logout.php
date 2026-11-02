<?php
require_once __DIR__ . '/includes/sesion.php';
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit;
