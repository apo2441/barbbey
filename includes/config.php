<?php
// ============================================
// CONFIGURATION - Edit these settings
// ============================================

// Database
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'barbershop_db');

// Site
define('SITE_URL', getenv('SITE_URL') ?: 'http://127.0.0.1:8000');
define('SITE_NAME', 'Barbbey');

// Email (PHPMailer settings)
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USER', getenv('MAIL_USER') ?: 'your@gmail.com');
define('MAIL_PASS', getenv('MAIL_PASS') ?: 'your_app_password');
define('MAIL_PORT', 587);
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'your@gmail.com');
define('MAIL_FROM_NAME', 'Barbbey');

// Booking
define('SLOT_DURATION', 30); // minutes
define('SEARCH_RADIUS_KM', 10);
define('TOP_RATED_COUNT', 3);

session_start();

// DB Connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
    'mysql:host='.DB_HOST.';port='.(getenv('DB_PORT') ?: '3306').';dbname='.DB_NAME.';charset=utf8mb4',
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);     } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Auth helpers
function isLoggedIn() { return isset($_SESSION['user_id']); }
function isBarber()   { return isset($_SESSION['barber_id']); }
function isAdmin()    { return isset($_SESSION['admin_id']); }

function requireLogin() {
    if (!isLoggedIn()) { header('Location: '.SITE_URL.'/login.php'); exit; }
}
function requireBarber() {
    if (!isBarber()) { header('Location: '.SITE_URL.'/barber/login.php'); exit; }
}
function requireAdmin() {
    if (!isAdmin()) { header('Location: '.SITE_URL.'/admin/login.php'); exit; }
}

// Haversine distance in km
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)*sin($dLon/2);
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}

// Generate 8-digit unique barber ID
function generateBarberId() {
    $db = getDB();
    do {
        $id = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $exists = $db->prepare('SELECT id FROM barbers WHERE barber_id = ?');
        $exists->execute([$id]);
    } while ($exists->fetch());
    return $id;
}

// Flash messages
function setFlash($type, $msg) { $_SESSION['flash'] = ['type'=>$type,'msg'=>$msg]; }
function getFlash() {
    if (isset($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
