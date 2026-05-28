<?php
// search.php — AJAX-поиск по врачам и услугам
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$like    = '%' . $q . '%';
$results = [];

// Поиск по врачам
$stmt = $db->prepare(
    "SELECT id, name, specialty FROM doctors
     WHERE is_active=1 AND (name LIKE ? OR specialty LIKE ?)
     LIMIT 4"
);
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($doctors as $d) {
    $results[] = [
        'type'     => 'doctor',
        'title'    => $d['name'],
        'subtitle' => $d['specialty'],
        'url'      => '/vetclinic/doctors.php',
    ];
}

// Поиск по услугам
$stmt = $db->prepare(
    "SELECT id, name, price FROM services
     WHERE is_active=1 AND (name LIKE ? OR description LIKE ?)
     LIMIT 4"
);
$stmt->bind_param('ss', $like, $like);
$stmt->execute();
$services = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($services as $s) {
    $results[] = [
        'type'     => 'service',
        'title'    => $s['name'],
        'subtitle' => number_format((float)$s['price'], 0, '.', ' ') . ' ₽',
        'url'      => '/vetclinic/prices.php',
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
