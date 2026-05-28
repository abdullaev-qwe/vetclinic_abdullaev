<?php
/**
 * waiting_list.php — функции работы с листом ожидания
 */

/** Период приёма */
function waitingTimePreferences(): array
{
    return [
        'any'       => 'Любое время',
        'morning'   => 'Утро (8:00–12:00)',
        'afternoon' => 'День (12:00–16:00)',
        'evening'   => 'Вечер (16:00–20:00)',
    ];
}

/** Статусы и их подписи */
function waitingStatusLabel(string $status): string
{
    $labels = [
        'waiting'   => 'Ожидает',
        'offered'   => 'Предложено место',
        'accepted'  => 'Записан',
        'expired'   => 'Просрочено',
        'cancelled' => 'Отменено',
    ];
    return $labels[$status] ?? $status;
}

function waitingStatusBadge(string $status): string
{
    $colors = [
        'waiting'   => ['bg' => '#fff8e1', 'fg' => '#8a6500', 'icon' => '⏳'],
        'offered'   => ['bg' => '#e3f2fd', 'fg' => '#0d47a1', 'icon' => '📩'],
        'accepted'  => ['bg' => '#e8f5e9', 'fg' => '#1b5e20', 'icon' => '✅'],
        'expired'   => ['bg' => '#f5f5f5', 'fg' => '#616161', 'icon' => '⌛'],
        'cancelled' => ['bg' => '#ffebee', 'fg' => '#b71c1c', 'icon' => '❌'],
    ];
    $c = $colors[$status] ?? $colors['waiting'];

    return '<span style="display:inline-flex;align-items:center;gap:4px;'
         . 'background:' . $c['bg'] . ';color:' . $c['fg'] . ';'
         . 'padding:3px 10px;border-radius:12px;font-size:.78rem;font-weight:600;'
         . 'white-space:nowrap;">'
         . $c['icon'] . ' ' . htmlspecialchars(waitingStatusLabel($status)) . '</span>';
}

/**
 * Создать заявку в лист ожидания
 *
 * @return array{ok:bool, errors?:string[], id?:int}
 */
function createWaitingListEntry(mysqli $db, int $userId, array $data): array
{
    $errors = [];

    $petName = trim($data['pet_name'] ?? '');
    $petType = trim($data['pet_type'] ?? '');

    if (mb_strlen($petName) < 1 || mb_strlen($petName) > 80) {
        $errors[] = 'Укажите имя питомца (1-80 символов)';
    }
    if (mb_strlen($petType) < 1 || mb_strlen($petType) > 40) {
        $errors[] = 'Укажите вид животного';
    }

    $doctorId  = !empty($data['doctor_id'])  ? (int)$data['doctor_id']  : null;
    $serviceId = !empty($data['service_id']) ? (int)$data['service_id'] : null;

    $dateFrom = !empty($data['preferred_date_from']) ? $data['preferred_date_from'] : null;
    $dateTo   = !empty($data['preferred_date_to'])   ? $data['preferred_date_to']   : null;

    if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $errors[] = 'Неверный формат даты "от"';
    }
    if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $errors[] = 'Неверный формат даты "до"';
    }
    if ($dateFrom && $dateTo && strtotime($dateFrom) > strtotime($dateTo)) {
        $errors[] = 'Дата "до" не может быть раньше даты "от"';
    }
    if ($dateFrom && strtotime($dateFrom) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Дата "от" не может быть в прошлом';
    }

    $time = $data['preferred_time'] ?? 'any';
    if (!array_key_exists($time, waitingTimePreferences())) {
        $time = 'any';
    }

    $notes = trim($data['notes'] ?? '');
    if (mb_strlen($notes) > 500) {
        $errors[] = 'Комментарий слишком длинный (макс. 500 символов)';
    }

    if (!empty($errors)) {
        return ['ok' => false, 'errors' => $errors];
    }

    // Проверяем нет ли уже активной заявки от этого пользователя на того же врача/услугу
    $stmt = $db->prepare(
        "SELECT id FROM waiting_list
         WHERE user_id = ? AND status = 'waiting'
           AND (doctor_id <=> ?) AND (service_id <=> ?)
         LIMIT 1"
    );
    $stmt->bind_param('iii', $userId, $doctorId, $serviceId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        return ['ok' => false, 'errors' => ['У вас уже есть активная заявка с такими параметрами.']];
    }

    $stmt = $db->prepare(
        "INSERT INTO waiting_list
         (user_id, doctor_id, service_id, pet_name, pet_type,
          preferred_date_from, preferred_date_to, preferred_time, notes, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting')"
    );
    $stmt->bind_param(
        'iiisssssss',
        $userId, $doctorId, $serviceId, $petName, $petType,
        $dateFrom, $dateTo, $time, $notes
    );
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();

    return ['ok' => true, 'id' => $id];
}

/**
 * Получить активные заявки в листе ожидания, подходящие для появившегося слота.
 *
 * @param mysqli $db
 * @param int|null $doctorId   Врач отменённой записи
 * @param int|null $serviceId  Услуга
 * @param string|null $date    Освободившаяся дата (YYYY-MM-DD)
 * @param string|null $time    Освободившееся время (HH:MM)
 * @return array
 */
function findMatchingWaitingEntries(
    mysqli $db,
    ?int $doctorId = null,
    ?int $serviceId = null,
    ?string $date = null,
    ?string $time = null
): array {
    // Подбираем заявки где:
    // - статус waiting
    // - врач совпадает ИЛИ заявка на любого врача (NULL)
    // - услуга совпадает ИЛИ заявка на любую (NULL)
    // - если указана дата — она попадает в указанный диапазон или диапазон не задан
    // - если указано время — оно попадает в выбранный период

    $where = ["status = 'waiting'"];
    $params = [];
    $types  = '';

    if ($doctorId !== null) {
        $where[] = '(doctor_id = ? OR doctor_id IS NULL)';
        $params[] = $doctorId;
        $types   .= 'i';
    }
    if ($serviceId !== null) {
        $where[] = '(service_id = ? OR service_id IS NULL)';
        $params[] = $serviceId;
        $types   .= 'i';
    }
    if ($date !== null) {
        $where[] = '(preferred_date_from IS NULL OR preferred_date_from <= ?)';
        $where[] = '(preferred_date_to   IS NULL OR preferred_date_to   >= ?)';
        $params[] = $date;
        $params[] = $date;
        $types   .= 'ss';
    }

    if ($time !== null && preg_match('/^(\d{2}):/', $time, $m)) {
        $hour = (int)$m[1];
        $period = 'any';
        if ($hour >= 8  && $hour < 12) $period = 'morning';
        elseif ($hour >= 12 && $hour < 16) $period = 'afternoon';
        elseif ($hour >= 16 && $hour < 20) $period = 'evening';

        $where[] = "(preferred_time = 'any' OR preferred_time = ?)";
        $params[] = $period;
        $types   .= 's';
    }

    $whereSQL = implode(' AND ', $where);
    $sql = "SELECT * FROM view_waiting_list WHERE $whereSQL ORDER BY created_at ASC";

    $stmt = $db->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

/**
 * Количество новых ожидающих (для бейджа в админке)
 */
function countNewWaitingEntries(mysqli $db): int
{
    $r = $db->query("SELECT COUNT(*) AS c FROM waiting_list WHERE status = 'waiting'");
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
}

/**
 * Сменить статус заявки
 */
function updateWaitingStatus(mysqli $db, int $entryId, string $newStatus): bool
{
    $allowed = ['waiting','offered','accepted','expired','cancelled'];
    if (!in_array($newStatus, $allowed)) return false;

    if ($newStatus === 'offered') {
        $stmt = $db->prepare("UPDATE waiting_list SET status = ?, notified_at = NOW() WHERE id = ?");
    } else {
        $stmt = $db->prepare("UPDATE waiting_list SET status = ? WHERE id = ?");
    }
    $stmt->bind_param('si', $newStatus, $entryId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
