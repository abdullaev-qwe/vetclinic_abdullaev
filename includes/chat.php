<?php
/**
 * chat.php — функции работы с чатом
 */

/**
 * Получить (или создать) тред для пользователя
 */
function getOrCreateChatThread(mysqli $db, int $userId): int
{
    $stmt = $db->prepare("SELECT id FROM chat_threads WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        return (int)$row['id'];
    }

    $stmt = $db->prepare("INSERT INTO chat_threads (user_id) VALUES (?)");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

/**
 * Получить тред пользователя (без создания)
 */
function getChatThreadByUser(mysqli $db, int $userId): ?array
{
    $stmt = $db->prepare("SELECT * FROM chat_threads WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Получить тред по ID (для админки)
 */
function getChatThreadById(mysqli $db, int $threadId): ?array
{
    $stmt = $db->prepare("SELECT * FROM view_chat_threads WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $threadId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Получить сообщения треда (с лимитом и опциональным since_id для polling)
 */
function getChatMessages(mysqli $db, int $threadId, int $sinceId = 0, int $limit = 100): array
{
    if ($sinceId > 0) {
        $stmt = $db->prepare(
            "SELECT id, sender, message, created_at FROM chat_messages
             WHERE thread_id = ? AND id > ? ORDER BY created_at ASC LIMIT ?"
        );
        $stmt->bind_param('iii', $threadId, $sinceId, $limit);
    } else {
        $stmt = $db->prepare(
            "SELECT id, sender, message, created_at FROM chat_messages
             WHERE thread_id = ? ORDER BY created_at ASC LIMIT ?"
        );
        $stmt->bind_param('ii', $threadId, $limit);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Отправить сообщение
 *
 * @return array{ok:bool, error?:string, message_id?:int}
 */
function sendChatMessage(mysqli $db, int $threadId, string $sender, ?int $senderId, string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return ['ok' => false, 'error' => 'Сообщение пустое'];
    }
    if (mb_strlen($text) > 2000) {
        return ['ok' => false, 'error' => 'Сообщение слишком длинное (макс. 2000 символов)'];
    }
    if (!in_array($sender, ['user', 'admin'], true)) {
        return ['ok' => false, 'error' => 'Некорректный отправитель'];
    }

    // Rate-limit: не более 30 сообщений в минуту от одного отправителя
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS cnt FROM chat_messages
         WHERE thread_id = ? AND sender = ?
           AND created_at > NOW() - INTERVAL 60 SECOND"
    );
    $stmt->bind_param('is', $threadId, $sender);
    $stmt->execute();
    $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    if ($cnt >= 30) {
        return ['ok' => false, 'error' => 'Слишком много сообщений. Подождите минуту.'];
    }

    // Вставка
    $stmt = $db->prepare(
        "INSERT INTO chat_messages (thread_id, sender, sender_id, message)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('isis', $threadId, $sender, $senderId, $text);
    $stmt->execute();
    $msgId = (int)$stmt->insert_id;
    $stmt->close();

    // Обновляем счётчики и время последнего сообщения
    if ($sender === 'user') {
        // юзер написал → админу непрочитанных +1
        $db->query("UPDATE chat_threads SET admin_unread = admin_unread + 1, last_message_at = NOW() WHERE id = $threadId");
    } else {
        // админ написал → юзеру непрочитанных +1
        $db->query("UPDATE chat_threads SET user_unread = user_unread + 1, last_message_at = NOW() WHERE id = $threadId");
    }

    return ['ok' => true, 'message_id' => $msgId];
}

/**
 * Сбросить счётчик непрочитанных у пользователя или админа
 */
function markChatRead(mysqli $db, int $threadId, string $who): void
{
    if ($who === 'user') {
        $db->query("UPDATE chat_threads SET user_unread = 0 WHERE id = $threadId");
    } else {
        $db->query("UPDATE chat_threads SET admin_unread = 0 WHERE id = $threadId");
    }
}

/**
 * Количество тредов с непрочитанными для админа (для бейджа в меню)
 */
function countUnreadChatThreads(mysqli $db): int
{
    $r = $db->query("SELECT COUNT(*) AS c FROM chat_threads WHERE admin_unread > 0");
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
}

/**
 * Получить список всех тредов (для админки)
 */
function getAllChatThreads(mysqli $db): array
{
    $r = $db->query(
        "SELECT * FROM view_chat_threads
         ORDER BY (admin_unread > 0) DESC, COALESCE(last_message_at, created_at) DESC"
    );
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
