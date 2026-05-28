<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdminAuth();

$currentPage = basename($_SERVER['PHP_SELF']);

function navIcon(string $name): string {
    $icons = [
        'dashboard' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>',
        'calendar'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>',
        'users'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'doctor'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/></svg>',
        'services'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>',
        'star'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        'log'       => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
        'globe'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>',
        'logout'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>',
    ];
    return $icons[$name] ?? '';
}

// Счётчик новых записей для бейджа
$newCount = (int)$db->query("SELECT COUNT(*) AS c FROM appointments WHERE status='new'")->fetch_assoc()['c'];
// Счётчик скрытых отзывов
$hiddenReviews = (int)$db->query("SELECT COUNT(*) AS c FROM reviews WHERE is_visible=0")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($adminPageTitle) ? e($adminPageTitle) . ' — Админ ВетЗабота' : 'Панель управления ВетЗабота' ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="/vetclinic/assets/css/admin.css">
  <link rel="stylesheet" href="/vetclinic/assets/css/theme.css">
  <?php
    // chat.css содержит стили для списка диалогов и окна беседы админа
    // (.chat-threads-list, .chat-thread-card, .chat-thread-avatar и т.д.).
    // Без него аватар клиента не получает размер 48×48 и img распирает страницу.
    if (in_array($currentPage, ['chats.php', 'chat_view.php'], true)):
      $chatCssPath = dirname(__DIR__, 2) . '/assets/css/chat.css';
      $chatCssVer  = is_file($chatCssPath) ? filemtime($chatCssPath) : 1;
  ?>
  <link rel="stylesheet" href="/vetclinic/assets/css/chat.css?v=<?= e((string)$chatCssVer) ?>">
  <?php endif; ?>
</head>
<body>

<div class="admin-layout">

  <aside class="admin-sidebar">
    <div class="admin-logo">
      <svg class="admin-logo-icon" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M8 6 L36 6 Q40 6 40 10 L40 26 Q40 38 22 43 Q4 38 4 26 L4 10 Q4 6 8 6 Z"
              fill="rgba(255,255,255,0.15)" stroke="rgba(255,255,255,0.5)" stroke-width="1.5"/>
        <rect x="18.5" y="14" width="7" height="18" rx="2" fill="white" opacity="0.9"/>
        <rect x="13" y="19.5" width="18" height="7" rx="2" fill="white" opacity="0.9"/>
        <circle cx="15" cy="9" r="3.5" fill="#c9a84c"/>
        <circle cx="22" cy="6" r="3"   fill="#c9a84c"/>
        <circle cx="29" cy="9" r="3.5" fill="#c9a84c"/>
      </svg>
      <div class="admin-logo-text">Вет<strong>Забота</strong><em>админ панель</em></div>
    </div>

    <nav class="admin-nav">
      <div class="admin-nav-label">Главное</div>

      <a href="/vetclinic/admin/index.php"
         class="<?= $currentPage==='index.php'?'active':'' ?>">
        <?= navIcon('dashboard') ?> Дашборд
      </a>

      <a href="/vetclinic/admin/appointments.php"
         class="<?= $currentPage==='appointments.php'?'active':'' ?>">
        <?= navIcon('calendar') ?> Записи на приём
        <?php if ($newCount > 0): ?>
          <span class="nav-badge"><?= $newCount ?></span>
        <?php endif; ?>
      </a>
<a href="/vetclinic/admin/waiting_list.php"
   class="admin-menu-link <?= str_contains($_SERVER['REQUEST_URI'], '/admin/waiting_list.php') ? 'active' : '' ?>">
   ⏳ Лист ожидания
   <?php
   $waitCount = 0;
   try {
       $wr = $db->query("SELECT COUNT(*) AS c FROM waiting_list WHERE status='waiting'");
       if ($wr) $waitCount = (int)$wr->fetch_assoc()['c'];
   } catch (\Throwable $e) {}
   if ($waitCount > 0): ?>
     <span class="admin-menu-badge"><?= $waitCount ?></span>
   <?php endif; ?>
</a>
<a href="/vetclinic/admin/chats.php"
   class="admin-menu-link <?= str_contains($_SERVER['REQUEST_URI'], '/admin/chats') ? 'active' : '' ?>">
   💬 Чаты
   <?php
   $chatUnread = 0;
   try {
       require_once __DIR__ . '/../../includes/chat.php';
       $chatUnread = countUnreadChatThreads($db);
   } catch (\Throwable $e) {}
   if ($chatUnread > 0): ?>
     <span class="admin-menu-badge"><?= $chatUnread ?></span>
   <?php endif; ?>
</a>

      <div class="admin-nav-label">Управление</div>

      <a href="/vetclinic/admin/users.php"
         class="<?= $currentPage==='users.php'?'active':'' ?>">
        <?= navIcon('users') ?> Пользователи
      </a>

      <a href="/vetclinic/admin/doctors.php"
         class="<?= $currentPage==='doctors.php'?'active':'' ?>">
        <?= navIcon('doctor') ?> Врачи
      </a>

      <a href="/vetclinic/admin/services.php"
         class="<?= $currentPage==='services.php'?'active':'' ?>">
        <?= navIcon('services') ?> Услуги
      </a>

      <a href="/vetclinic/admin/reviews.php"
         class="<?= $currentPage==='reviews.php'?'active':'' ?>">
        <?= navIcon('star') ?> Отзывы
        <?php if ($hiddenReviews > 0): ?>
          <span class="nav-badge"><?= $hiddenReviews ?></span>
        <?php endif; ?>
      </a>

      <a href="/vetclinic/admin/schedule.php"
         class="<?= $currentPage==='schedule.php'?'active':'' ?>">
        <?= navIcon('calendar') ?> Расписание

      <a href="/vetclinic/admin/logs.php"
         class="<?= $currentPage==='logs.php'?'active':'' ?>">
        <?= navIcon('log') ?> Журнал действий
      </a>

      <div class="admin-nav-divider"></div>

      <a href="/vetclinic/index.php" target="_blank"><?= navIcon('globe') ?> На сайт</a>
      <a href="/vetclinic/admin/logout.php" class="nav-logout"><?= navIcon('logout') ?> Выйти</a>
    </nav>
  </aside>

  <div class="admin-content">
    <div class="admin-topbar">
      <h1 class="admin-page-title">
        <?= isset($adminPageTitle) ? e($adminPageTitle) : 'Панель управления' ?>
      </h1>
      <div class="admin-user-badge">
        <div class="admin-user-avatar">
          <?= mb_strtoupper(mb_substr($_SESSION['admin_username'] ?? 'A', 0, 1)) ?>
        </div>
        <span class="admin-user-name">
          <?= e($_SESSION['admin_username'] ?? 'Администратор') ?>
        </span>
      </div>
    </div>

    <div class="admin-main">
      <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
      <?php endif; ?>
