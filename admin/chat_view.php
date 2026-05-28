<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/chat.php';
require_once __DIR__ . '/../includes/avatar.php';

$threadId = (int)($_GET['id'] ?? 0);
$thread = getChatThreadById($db, $threadId);

if (!$thread) {
    setFlash('danger', 'Диалог не найден');
    redirect('/vetclinic/admin/chats.php');
}

$adminPageTitle = 'Чат с ' . $thread['user_name'];

// ── Отправка ответа ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $csrfValid = false;
    if (function_exists('checkCsrfToken')) {
        $csrfValid = checkCsrfToken($csrfToken);
    } elseif (function_exists('verifyCsrf')) {
        try { verifyCsrf(); $csrfValid = true; } catch (\Throwable $e) {}
    } else {
        $csrfValid = !empty($_SESSION['csrf_token'])
                  && hash_equals($_SESSION['csrf_token'], $csrfToken);
    }

    if ($csrfValid) {
        $text = $_POST['text'] ?? '';
        $adminId = (int)($_SESSION['admin_id'] ?? 0);
        $result = sendChatMessage($db, $threadId, 'admin', $adminId, $text);
        if (!$result['ok']) {
            setFlash('danger', $result['error']);
        } else {
            logAdminAction($db, 'Ответил в чате с пользователем #' . (int)$thread['user_id']);
        }
    }
    redirect('/vetclinic/admin/chat_view.php?id=' . $threadId);
}

// Сбрасываем счётчик непрочитанных у админа
markChatRead($db, $threadId, 'admin');

// Загружаем все сообщения
$messages = getChatMessages($db, $threadId, 0, 500);

$csrfToken = function_exists('generateCsrfToken')
    ? generateCsrfToken()
    : ($_SESSION['csrf_token'] ?? '');

require_once __DIR__ . '/includes/admin_header.php';
?>

<div style="margin-bottom:14px;">
  <a href="/vetclinic/admin/chats.php" class="btn btn-outline btn-sm">← Все чаты</a>
</div>

<?php showFlash(); ?>

<div class="chat-admin-info">
  <div class="chat-thread-avatar"
       style="width:48px;height:48px;flex:0 0 48px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;">
    <?php
    // Inline-страховка размера: при отсутствии chat.css img не распирает страницу.
    $avatarUrl = avatarUrl($thread['user_avatar'] ?? null);
    if ($avatarUrl):
    ?>
      <img src="<?= e($avatarUrl) ?>" alt=""
           width="48" height="48"
           style="width:48px;height:48px;border-radius:50%;object-fit:cover;display:block;">
    <?php else:
      echo e(avatarInitials($thread['user_name'] ?? '?'));
    endif; ?>
  </div>
  <div>
    <strong><?= e($thread['user_name']) ?></strong>
    <small>
      📧 <?= e($thread['user_email']) ?>
      <?php if (!empty($thread['user_phone'])): ?>
        · 📞 <a href="tel:<?= e($thread['user_phone']) ?>"><?= e($thread['user_phone']) ?></a>
      <?php endif; ?>
    </small>
  </div>
</div>

<div class="chat-admin-window">
  <div class="chat-messages" id="adminChatMessages">
    <?php if (empty($messages)): ?>
      <div class="chat-empty">
        <p>Нет сообщений</p>
      </div>
    <?php else:
      $lastDate = null;
      foreach ($messages as $msg):
        $msgDate = date('d.m.Y', strtotime($msg['created_at']));
        if ($lastDate !== $msgDate):
          $lastDate = $msgDate;
          $today = date('d.m.Y');
          $yesterday = date('d.m.Y', strtotime('-1 day'));
          $label = $msgDate === $today ? 'Сегодня' : ($msgDate === $yesterday ? 'Вчера' : $msgDate);
    ?>
          <div class="chat-date-divider"><?= e($label) ?></div>
    <?php endif; ?>

      <div class="chat-msg chat-msg-<?= $msg['sender'] === 'admin' ? 'user' : 'admin' ?>">
        <?= nl2br(e($msg['message'])) ?>
        <span class="chat-msg-time">
          <?= date('H:i', strtotime($msg['created_at'])) ?>
          <?= $msg['sender'] === 'admin' ? '· вы' : '' ?>
        </span>
      </div>

    <?php
      endforeach;
    endif; ?>
  </div>

  <form method="POST" action="/vetclinic/admin/chat_view.php?id=<?= $threadId ?>"
        class="chat-form">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <textarea name="text" placeholder="Ваш ответ клиенту..."
              rows="1" maxlength="2000" required style="min-height:38px;"></textarea>
    <button type="submit" id="chatSend">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="22" y1="2" x2="11" y2="13"/>
        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
      </svg>
    </button>
  </form>
</div>

<script nonce="<?= e($cspNonce ?? '') ?>">
// Прокручиваем чат вниз при загрузке
(function() {
    var msgs = document.getElementById('adminChatMessages');
    if (msgs) msgs.scrollTop = msgs.scrollHeight;
})();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
