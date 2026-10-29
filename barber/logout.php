<?php
require_once '../includes/config.php';
session_destroy();
header('Location: '.SITE_URL.'/barber/login.php');
exit;
