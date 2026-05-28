<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/chat.php';
require_once __DIR__ . '/../includes/avatar.php';

$adminPageTitle = 'Чаты с клиентами';

$threads = getAllChatThreads($db);

require_once __DIR__ . '/includes/admin_header.php';
?>

<div style="margin-bottom:18px;">
  <p style="color:var(--ink-soft);">
    Всего диалогов: <strong><?= count($threads) ?></strong>
    <?php
    $unreadCount = 0;
    foreach ($threads as $t) if ((int)$t['admin_unread'] > 0) $unreadCount++;
    if ($unreadCount > 0):
    ?>
      · С непрочитанными:
      <strong style="color:#e74c3c;"><?= $unreadCount ?></strong>
    <?php endif; ?>
  </p>
</div>

<?php if (empty($threads)): ?>
  <div class="empty-state" style="text-align:center;padding:40px;background:var(--white);border-radius:12px;border:1px solid var(--border);">
    <p style="font-size:1.05rem;color:var(--ink-soft);">Пока ни один пользователь не писал в чат</p>
  </div>
<?php else: ?>
  <div class="chat-threads-list">
    <?php foreach ($threads as $t): ?>
      <a href="/vetclinic/admin/chat_view.php?id=<?= (int)$t['id'] ?>"
         class="chat-thread-card <?= (int)$t['admin_unread'] > 0 ? 'has-unread' : '' ?>">

        <div class="chat-thread-avatar"
             style="width:48px;height:48px;flex:0 0 48px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;">
          <?php
          // Используем аватар если есть, иначе инициалы.
          // Размеры заданы и на контейнере, и на img — чтобы вёрстка не ломалась
          // даже если chat.css не успел загрузиться.
          $avatarUrl = avatarUrl($t['user_avatar'] ?? null);
          if ($avatarUrl):
          ?>
            <img src="<?= e($avatarUrl) ?>" alt=""
                 width="48" height="48"
                 style="width:48px;height:48px;border-radius:50%;object-fit:cover;display:block;">
          <?php else:
            echo e(avatarInitials($t['user_name'] ?? '?'));
          endif; ?>
        </div>

        <div class="chat-thread-body" style="flex:1;min-width:0;">
          <div class="chat-thread-name">
            <strong>
              <?= e($t['user_name']) ?>
              <?php if ((int)$t['admin_unread'] > 0): ?>
                <span class="chat-thread-unread"><?= (int)$t['admin_unread'] ?></span>
              <?php endif; ?>
            </strong>
            <span class="chat-thread-time">
              <?php
              $time = $t['last_message_at'] ?? $t['created_at'];
              $ts = strtotime($time);
              $today = strtotime(date('Y-m-d'));
              if ($ts >= $today) {
                  echo date('H:i', $ts);
              } elseif ($ts >= $today - 86400) {
                  echo 'вчера';
              } else {
                  echo date('d.m', $ts);
              }
              ?>
            </span>
          </div>
          <div class="chat-thread-preview">
            <?php
            if ($t['last_message']) {
                $prefix = $t['last_sender'] === 'admin' ? '↩ ' : '';
                echo $prefix . e(mb_strimwidth($t['last_message'], 0, 80, '…'));
            } else {
                echo '<em style="color:var(--ink-muted);">диалог пуст</em>';
            }
            ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
