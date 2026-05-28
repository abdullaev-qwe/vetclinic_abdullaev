<?php
// =====================================================
// appointment_cancel.php — Отмена записи пользователем
// =====================================================
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

requireUserAuth();

$userId = (int)$_SESSION['user_id'];
$apptId = (int)($_GET['id'] ?? 0);

if ($apptId <= 0) {
    redirect('/vetclinic/appointments.php');
}

// Проверяем, что запись принадлежит этому пользователю и её можно отменить
$stmt = $db->prepare(
    "SELECT id, status FROM appointments
     WHERE id = ? AND user_id = ? AND status IN ('new','confirmed')
     LIMIT 1"
);
$stmt->bind_param('ii', $apptId, $userId);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appt) {
    setFlash('danger', 'Запись не найдена или её нельзя отменить.');
    redirect('/vetclinic/appointments.php');
}

// Отменяем запись
$stmt = $db->prepare(
    "UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ?"
);
$stmt->bind_param('ii', $apptId, $userId);
$stmt->execute();
$stmt->close();

setFlash('success', 'Запись успешно отменена.');
redirect('/vetclinic/appointments.php');
