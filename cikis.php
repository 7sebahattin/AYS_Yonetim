<?php
// cikis.php — ÇIKIŞ
require_once 'config.php';
require_once 'includes/functions.php';
oturum_baslat();
session_destroy();
// Çıkış sonrası herkese açık tanıtım sayfasına dön (giriş için: /login.php)
header('Location: /index.php');
exit;
