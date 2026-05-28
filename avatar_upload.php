<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/avatar.php';

requireUserAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/vetclinic/profile.php');
}

// ── Универсальная проверка CSRF ──
// Поддерживает разные имена функций которые могут быть в проекте
$csrfToken = $_POST['csrf_token'] ?? '';
$csrfValid = false;

if (function_exists('checkCsrfToken')) {
    $csrfValid = checkCsrfToken($csrfToken);
} elseif (function_exists('verifyCsrf')) {
    // verifyCsrf обычно бросает исключение при невалидном токене
    try {
        verifyCsrf();
        $csrfValid = true;
    } catch (\Throwable $e) {
        $csrfValid = false;
    }
} elseif (function_exists('validateCsrfToken')) {
    $csrfValid = validateCsrfToken($csrfToken);
} elseif (function_exists('verifyCsrfToken')) {
    $csrfValid = verifyCsrfToken($csrfToken);
} else {
    // Fallback: ручная проверка через сессию
    $csrfValid = !empty($_SESSION['csrf_token']) 
              && !empty($csrfToken)
              && hash_equals($_SESSION['csrf_token'], $csrfToken);
}

if (!$csrfValid) {
    setFlash('danger', 'Ошибка безопасности. Попробуйте снова.');
    redirect('/vetclinic/profile.php');
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// Получаем текущий аватар (нужно для удаления старого файла)
$stmt = $db->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();
$oldAvatar = $current['avatar'] ?? null;

if ($action === 'upload') {
    $result = uploadAvatar($_FILES['avatar'] ?? [], $userId);

    if (!$result['ok']) {
        setFlash('danger', 'Ошибка: ' . $result['error']);
        redirect('/vetclinic/profile.php');
    }

    // Сохраняем имя файла в БД
    $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->bind_param('si', $result['filename'], $userId);
    $stmt->execute();
    $stmt->close();

    // Удаляем старый файл если был
    if ($oldAvatar) {
        deleteAvatarFile($oldAvatar);
    }

    setFlash('success', 'Аватар обновлён!');
    redirect('/vetclinic/profile.php');
}

if ($action === 'delete') {
    if ($oldAvatar) {
        deleteAvatarFile($oldAvatar);
    }
    $stmt = $db->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    setFlash('success', 'Аватар удалён');
    redirect('/vetclinic/profile.php');
}

redirect('/vetclinic/profile.php');
