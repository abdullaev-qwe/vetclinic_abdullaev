<?php
/**
 * admin/export_xlsx.php — Экспорт записей в Excel
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/xlsx_writer.php';

// Проверка авторизации админа
if (empty($_SESSION['admin_id'])) {
    header('Location: /vetclinic/admin/login.php');
    exit;
}

// Получаем все записи через view
$rows = $db->query(
    "SELECT * FROM view_all_appointments ORDER BY appointment_date DESC, appointment_time DESC"
)->fetch_all(MYSQLI_ASSOC);

// Создаём Excel-файл
$xlsx = new XlsxWriter();
$xlsx->setHeaders([
    '#',
    'Дата приёма',
    'Время',
    'Пользователь',
    'Email',
    'Телефон',
    'Питомец',
    'Тип питомца',
    'Врач',
    'Услуга',
    'Цена, ₽',
    'Статус',
    'Комментарий',
    'Создано',
]);

$statusLabels = [
    'new'       => 'Новая',
    'confirmed' => 'Подтверждена',
    'cancelled' => 'Отменена',
    'completed' => 'Завершена',
];

foreach ($rows as $r) {
    $statusKey   = $r['status'] ?? '';
    $statusLabel = $statusLabels[$statusKey] ?? $statusKey;
    $rowStyle    = 'status_' . $statusKey;

    $xlsx->addRow([
        (int)$r['id'],
        date('d.m.Y', strtotime($r['appointment_date'])),
        substr($r['appointment_time'], 0, 5),
        $r['user_name'] ?? '',
        $r['user_email'] ?? '',
        $r['user_phone'] ?? '',
        $r['pet_name'] ?? '',
        $r['pet_type'] ?? '',
        $r['doctor_name'] ?? '',
        $r['service_name'] ?? '',
        $r['service_price'] ?? '',
        $statusLabel,
        $r['comment'] ?? '',
        date('d.m.Y H:i', strtotime($r['created_at'])),
    ], $rowStyle);
}

// Логируем действие админа
if (function_exists('logAdminAction')) {
    @logAdminAction($db, $_SESSION['admin_id'], 'Экспорт записей в Excel');
}

// Имя файла с датой
$filename = 'vetcare_appointments_' . date('Y-m-d_His') . '.xlsx';
$xlsx->download($filename);
