<?php
require_once '../includes/config.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['error'=>'Unauthorized']); exit; }

$q    = trim($_GET['q'] ?? '');
$type = $_GET['type'] ?? 'shop';
if (strlen($q) < 1) { echo json_encode(['results'=>[]]); exit; }

$db = getDB();
$results = [];

if ($type === 'shop') {
    $stmt = $db->prepare("SELECT s.*, COALESCE(AVG(r.rating),0) as avg_rating FROM barbershops s LEFT JOIN reviews r ON r.shop_id = s.id WHERE s.is_active = 1 AND s.name LIKE ? GROUP BY s.id LIMIT 20");
    $stmt->execute(['%'.$q.'%']);
    $results = $stmt->fetchAll();
} elseif ($type === 'barber') {
    $stmt = $db->prepare("SELECT DISTINCT s.id, s.name, s.address, s.city, s.phone, s.latitude, s.longitude, COALESCE(AVG(r.rating),0) as avg_rating FROM barbershops s JOIN barbers b ON b.shop_id = s.id LEFT JOIN reviews r ON r.shop_id = s.id WHERE s.is_active = 1 AND b.is_active = 1 AND b.name LIKE ? GROUP BY s.id LIMIT 20");
    $stmt->execute(['%'.$q.'%']);
    $results = $stmt->fetchAll();
} elseif ($type === 'id') {
    if (preg_match('/^\d{8}$/', $q)) {
        $stmt = $db->prepare("SELECT DISTINCT s.id, s.name, s.address, s.city, s.phone, s.latitude, s.longitude, COALESCE(AVG(r.rating),0) as avg_rating FROM barbershops s JOIN barbers b ON b.shop_id = s.id LEFT JOIN reviews r ON r.shop_id = s.id WHERE s.is_active = 1 AND b.barber_id = ? GROUP BY s.id");
        $stmt->execute([$q]);
        $results = $stmt->fetchAll();
    } else {
        echo json_encode(['results'=>[], 'message'=>'Barber ID must be 8 digits']); exit;
    }
}

echo json_encode(['results' => $results]);
