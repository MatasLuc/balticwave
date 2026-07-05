<?php
require_once __DIR__ . '/includes/boot.php';
logout();
header('Location: login.php');
exit;
