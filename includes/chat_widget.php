<?php
/**
 * chat_widget.php — встраиваемый виджет чата
 * Подключается из footer.php только для авторизованных пользователей
 */

if (!function_exists('isUserLoggedIn') || !isUserLoggedIn()) {
    return;
}

$chatCsrfToken = function_exists('generateCsrfToken')
    ? generateCsrfToken()
    : ($_SESSION['csrf_token'] ?? '');
?>

<!-- ═══ Чат-виджет ═══ -->
<div id="chatWidget" class="chat-widget" data-csrf="<?= e($chatCsrfToken) ?>">

  <!-- Плавающая кнопка -->
  <button type="button" id="chatToggle" class="chat-toggle" aria-label="Открыть чат">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
    </svg>
    <span id="chatUnreadBadge" class="chat-unread-badge" hidden>0</span>
  </button>

  <!-- Окно чата -->
  <div id="chatWindow" class="chat-window" hidden>
    <div class="chat-header">
      <div class="chat-header-info">
        <div class="chat-header-avatar">🐾</div>
        <div>
          <strong>ВетЗабота</strong>
          <small>Поддержка клиники</small>
        </div>
      </div>
      <button type="button" id="chatClose" class="chat-close" aria-label="Закрыть">
        ✕
      </button>
    </div>

    <div id="chatMessages" class="chat-messages">
      <div class="chat-empty">
        <p>👋 Здравствуйте! Напишите нам, и мы ответим в рабочее время<br>
        (Пн–Пт 9:00–20:00)</p>
      </div>
    </div>

    <form id="chatForm" class="chat-form">
      <textarea id="chatInput" placeholder="Введите сообщение..."
                rows="1" maxlength="2000" required></textarea>
      <button type="submit" id="chatSend" aria-label="Отправить">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="22" y1="2" x2="11" y2="13"/>
          <polygon points="22 2 15 22 11 13 2 9 22 2"/>
        </svg>
      </button>
    </form>
  </div>
</div>

<?php
// __DIR__ = .../includes, поэтому путь к chat.js нужно искать на уровень выше.
// Раньше путь был некорректным → filemtime() возвращал false и (при включённом
// display_errors) ломал атрибут src тегом-предупреждением: чат не закрывался,
// сообщения не отправлялись из-за того, что скрипт не загружался / был старым.
$chatJsPath = dirname(__DIR__) . '/assets/js/chat.js';
$chatJsVer  = is_file($chatJsPath) ? filemtime($chatJsPath) : 1;
?>
<script src="/vetclinic/assets/js/chat.js?v=<?= e((string)$chatJsVer) ?>"
        nonce="<?= e($cspNonce ?? '') ?>"></script>
