<?php
// cikis.php — ÇIKIŞ
require_once 'config.php';
require_once 'includes/functions.php';
oturum_baslat();
session_destroy();
header('Location: /index.php');
exit;
