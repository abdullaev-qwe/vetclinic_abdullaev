<?php
// notifications.php — Уведомления об изменении статуса записей
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isUserLoggedIn()) {
    echo json_encode(['count' => 0, 'items' => []]);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Последние изменения статусов за 7 дней
$stmt = $db->prepare(
    "SELECT a.id, a.status, a.appointment_date, s.name AS service_name
     FROM appointments a
     JOIN services s ON a.service_id = s.id
     WHERE a.user_id = ?
       AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
     ORDER BY a.created_at DESC
     LIMIT 5"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$items = [];
foreach ($rows as $r) {
    $statusLabels = [
        'new'       => 'Новая запись создана',
        'confirmed' => 'Запись подтверждена',
        'cancelled' => 'Запись отменена',
        'completed' => 'Приём завершён',
    ];
    $items[] = [
        'title'  => $statusLabels[$r['status']] ?? $r['status'],
        'text'   => $r['service_name'],
        'date'   => $r['appointment_date'],
        'status' => $r['status'],
    ];
}

echo json_encode([
    'count' => count($items),
    'items' => $items,
], JSON_UNESCAPED_UNICODE);
