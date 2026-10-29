<?php
require_once 'includes/config.php';
if (isLoggedIn())  { header('Location: '.SITE_URL.'/client/dashboard.php'); exit; }
if (isBarber())    { header('Location: '.SITE_URL.'/barber/dashboard.php'); exit; }
if (isAdmin())     { header('Location: '.SITE_URL.'/admin/dashboard.php'); exit; }
header('Location: '.SITE_URL.'/login.php'); exit;
