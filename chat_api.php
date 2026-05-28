<?php
/**
 * chat_api.php — AJAX endpoint для чата
 *
 * GET  ?since_id=N  — получить новые сообщения и счётчик непрочитанных
 * POST text=...     — отправить сообщение (CSRF обязателен)
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/chat.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Только для авторизованных
if (!isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Требуется авторизация']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ── GET: получить сообщения ──
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $thread = getChatThreadByUser($db, $userId);
    if (!$thread) {
        // Тред ещё не создан — пустой ответ
        echo json_encode([
            'ok'        => true,
            'messages'  => [],
            'unread'    => 0,
            'thread_id' => 0,
        ]);
        exit;
    }

    $sinceId = (int)($_GET['since_id'] ?? 0);
    $messages = getChatMessages($db, (int)$thread['id'], $sinceId);

    // Если запрос с открытым окном — сбрасываем счётчик у пользователя
    $markAsRead = !empty($_GET['mark_read']);
    if ($markAsRead) {
        markChatRead($db, (int)$thread['id'], 'user');
        $thread['user_unread'] = 0;
    }

    // Безопасно экранируем сообщения для вывода
    foreach ($messages as &$m) {
        $m['message_html'] = nl2br(htmlspecialchars($m['message']));
        $m['time_label']   = date('H:i', strtotime($m['created_at']));
        $m['date_label']   = date('d.m.Y', strtotime($m['created_at']));
    }

    echo json_encode([
        'ok'        => true,
        'thread_id' => (int)$thread['id'],
        'messages'  => $messages,
        'unread'    => (int)$thread['user_unread'],
    ]);
    exit;
}

// ── POST: отправить сообщение ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF: для чата нужен один и тот же токен на много сообщений за сессию,
    // поэтому не используем verifyCsrf() — она ротирует токен и die()-ит HTML
    // (на повторных POST второе сообщение всегда падало бы и приходил HTML
    //  вместо JSON, что в JS обрабатывалось как «сетевая ошибка»).
    // Проверяем токен напрямую через hash_equals, не меняя его в сессии.
    $csrfToken    = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $csrfValid    = !empty($csrfToken)
                 && !empty($sessionToken)
                 && hash_equals($sessionToken, $csrfToken);

    if (!$csrfValid) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Недействительный CSRF-токен']);
        exit;
    }

    $text = $_POST['text'] ?? '';
    $threadId = getOrCreateChatThread($db, $userId);
    $result = sendChatMessage($db, $threadId, 'user', $userId, $text);

    if (!$result['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $result['error']]);
        exit;
    }

    echo json_encode([
        'ok'         => true,
        'thread_id'  => $threadId,
        'message_id' => $result['message_id'],
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
