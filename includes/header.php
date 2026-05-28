<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
// icons.php — единая система SVG-иконок. Подключаем в шапке, чтобы
// функция icon() была доступна и в footer.php, и на любой странице,
// где раньше использовались эмодзи (📅 📍 📞 ✉️ 🕐 👤 …).
require_once __DIR__ . '/icons.php';

sendSecurityHeaders();
generateCsrfToken();
$cspNonce = generateCspNonce();

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? e($pageTitle) . ' — ВетЗабота' : 'ВетЗабота — Ветеринарная клиника' ?></title>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 44 44'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='44' y2='44' gradientUnits='userSpaceOnUse'%3E%3Cstop offset='0%25' stop-color='%230d4f3c'/%3E%3Cstop offset='100%25' stop-color='%231e8a6a'/%3E%3C/linearGradient%3E%3C/defs%3E%3Cpath d='M8 6 L36 6 Q40 6 40 10 L40 26 Q40 38 22 43 Q4 38 4 26 L4 10 Q4 6 8 6 Z' fill='url(%23g)'/%3E%3Crect x='18.5' y='14' width='7' height='18' rx='2' fill='white' opacity='.95'/%3E%3Crect x='13' y='19.5' width='18' height='7' rx='2' fill='white' opacity='.95'/%3E%3Ccircle cx='15' cy='9' r='3.5' fill='%23c9a84c'/%3E%3Ccircle cx='22' cy='6' r='3' fill='%23c9a84c'/%3E%3Ccircle cx='29' cy='9' r='3.5' fill='%23c9a84c'/%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="/vetclinic/assets/css/style.css">
  <link rel="stylesheet" href="/vetclinic/assets/css/theme.css">
  <link rel="stylesheet" href="/vetclinic/assets/css/appointment-form.css?v=1777231364">
  <link rel="stylesheet" href="/vetclinic/assets/css/waiting-list.css?v=1777269724">
  <?php
    // Cache-buster по mtime: иначе браузер держит старый chat.css в кэше и
    // правки (например, .chat-window[hidden]{display:none}) не доходят.
    $chatCssAbs = dirname(__DIR__) . '/assets/css/chat.css';
    $chatCssV   = is_file($chatCssAbs) ? filemtime($chatCssAbs) : time();
  ?>
  <link rel="stylesheet" href="/vetclinic/assets/css/chat.css?v=<?= e((string)$chatCssV) ?>">
  <link rel="stylesheet" href="/vetclinic/assets/css/avatar.css?v=1777190304">
  <?php
    // Динамический cache-buster для всех theme-файлов: без него браузер
    // продолжает использовать старые версии без правил для тёмной темы,
    // и текст на section-title и т.п. остаётся еле читаемым.
    $themeBase = dirname(__DIR__) . '/assets/css';
    $tFinal = is_file($themeBase.'/theme-dark-final.css')     ? filemtime($themeBase.'/theme-dark-final.css')     : time();
    $tHero  = is_file($themeBase.'/theme-pagehero-final.css') ? filemtime($themeBase.'/theme-pagehero-final.css') : time();
    $tFix   = is_file($themeBase.'/theme-dark-fix.css')       ? filemtime($themeBase.'/theme-dark-fix.css')       : time();
  ?>
  <link rel="stylesheet" href="/vetclinic/assets/css/theme-dark-final.css?v=<?= e((string)$tFinal) ?>">
  <link rel="stylesheet" href="/vetclinic/assets/css/theme-pagehero-final.css?v=<?= e((string)$tHero) ?>">
  <link rel="stylesheet" href="/vetclinic/assets/css/skeleton.css">
  <!-- theme-dark-fix.css — финальный страховочный слой для тёмной темы.
       Должен быть подключён ПОСЛЕДНИМ, чтобы перебить любые предыдущие правила. -->
  <link rel="stylesheet" href="/vetclinic/assets/css/theme-dark-fix.css?v=<?= e((string)$tFix) ?>">
  <style nonce="<?= e($cspNonce) ?>">
  body { overflow-x:hidden; }

  /* ── Шапка ── */
  .site-header {
    position:sticky; top:0; z-index:200;
    background:rgba(250,248,244,.96);
    backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
    border-bottom:1px solid var(--border);
  }
  .site-header .container {
    display:flex; align-items:center; height:72px; gap:0;
  }

  /* Логотип */
  .hdr-logo { display:flex;align-items:center;gap:9px;text-decoration:none;flex-shrink:0; }
  .hdr-logo-txt { font-family:var(--font-display);font-size:1.4rem;font-weight:700;color:var(--ink);letter-spacing:-.01em;white-space:nowrap; }
  .hdr-logo-txt strong { color:var(--emerald); }
  .hdr-logo-txt em { font-style:italic;color:var(--gold);font-weight:400; }

  /* Навигация */
  .hdr-nav { flex:1;min-width:0;margin:0 12px; }
  .hdr-nav ul { display:flex;align-items:center;justify-content:center;gap:0;list-style:none;padding:0;margin:0; }
  .hdr-nav a { display:block;padding:6px 9px;border-radius:7px;font-size:.8rem;font-weight:500;color:var(--ink-soft);white-space:nowrap;transition:background .15s,color .15s;text-decoration:none; }
  .hdr-nav a:hover, .hdr-nav a.active { color:var(--emerald);background:var(--emerald-ghost); }

  /* Правая часть */
  .hdr-right { display:flex;align-items:center;gap:7px;flex-shrink:0; }
  .hdr-right .btn { padding:7px 14px;font-size:.77rem; }
  .hdr-right .btn-gold { padding:7px 16px; }

  /* Кнопка поиска */
  .hdr-search-btn {
    width:34px;height:34px;border:1.5px solid var(--border);border-radius:50%;
    background:var(--white);display:flex;align-items:center;justify-content:center;
    cursor:pointer;color:var(--ink-soft);flex-shrink:0;
    transition:border-color .15s,color .15s;
  }
  .hdr-search-btn:hover { border-color:var(--emerald);color:var(--emerald); }

  /* Бургер */
  .hdr-burger { display:none;flex-direction:column;gap:5px;background:none;border:none;cursor:pointer;padding:5px;margin-left:6px; }
  .hdr-burger span { display:block;width:21px;height:2px;background:var(--ink);border-radius:2px;transition:.25s; }
  .hdr-burger.open span:nth-child(1){ transform:translateY(7px) rotate(45deg); }
  .hdr-burger.open span:nth-child(2){ opacity:0; }
  .hdr-burger.open span:nth-child(3){ transform:translateY(-7px) rotate(-45deg); }

  /* Уведомления */
  .hdr-notif { position:relative;flex-shrink:0; }
  .hdr-notif-btn { width:34px;height:34px;border:1.5px solid var(--border);border-radius:50%;background:var(--white);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--ink-soft);transition:border-color .15s; }
  .hdr-notif-btn:hover { border-color:var(--emerald);color:var(--emerald); }
  .hdr-notif-dot { position:absolute;top:-1px;right:-1px;width:8px;height:8px;background:#e74c3c;border-radius:50%;border:2px solid var(--cream);display:none; }
  .hdr-notif-drop { position:absolute;top:calc(100%+8px);right:0;width:300px;background:var(--white);border-radius:var(--radius-lg);box-shadow:var(--shadow-xl);border:1px solid var(--border);z-index:300;overflow:hidden;display:none; }
  .hdr-notif-drop.open { display:block; }
  .hdr-notif-hdr { padding:12px 16px;border-bottom:1px solid var(--border);font-weight:700;font-size:.86rem;display:flex;justify-content:space-between;align-items:center; }
  .hdr-notif-item { padding:11px 16px;border-bottom:1px solid var(--border);transition:.15s; }
  .hdr-notif-item:last-child { border-bottom:none; }
  .hdr-notif-item:hover { background:var(--emerald-ghost); }
  .hdr-notif-title { font-size:.84rem;font-weight:600;color:var(--ink);margin-bottom:2px; }
  .hdr-notif-text  { font-size:.76rem;color:var(--ink-muted); }
  .hdr-notif-empty { padding:22px 16px;text-align:center;color:var(--ink-muted);font-size:.84rem; }

  /* Поиск — оверлей поверх страницы */
  .hdr-search-overlay {
    display:none;
    position:fixed; top:72px; left:0; right:0; z-index:9999;
    background:rgba(250,248,244,.98);
    border-bottom:1px solid var(--border);
    padding:16px 0;
    box-shadow:0 8px 32px rgba(13,79,60,.12);
  }
  .hdr-search-overlay.open { display:block; }
  .hdr-search-inner { position:relative;max-width:620px;margin:0 auto;padding:0 24px; }
  .hdr-search-inner input {
    width:100%;padding:12px 44px 12px 20px;
    border:1.5px solid var(--border);border-radius:30px;
    font-family:var(--font-body);font-size:.92rem;
    background:var(--white);outline:none;
    transition:border-color .15s,box-shadow .15s;
  }
  .hdr-search-inner input:focus { border-color:var(--emerald);box-shadow:0 0 0 4px rgba(13,79,60,.08); }
  .hdr-search-ico { position:absolute;right:38px;top:50%;transform:translateY(-50%);color:var(--ink-muted);pointer-events:none; }
  .hdr-search-results { position:absolute;top:calc(100%+6px);left:24px;right:24px;background:var(--white);border-radius:var(--radius-lg);box-shadow:var(--shadow-lg);border:1px solid var(--border);overflow:hidden;display:none;z-index:10000; }
  .hdr-search-results.open { display:block; }
  .hdr-search-item { display:flex;align-items:center;gap:12px;padding:11px 16px;border-bottom:1px solid var(--border);text-decoration:none;transition:.15s; }
  .hdr-search-item:last-child { border-bottom:none; }
  .hdr-search-item:hover { background:var(--emerald-ghost); }
  .hdr-search-item-icon { width:32px;height:32px;background:var(--emerald-ghost);border-radius:7px;display:flex;align-items:center;justify-content:center;color:var(--emerald);flex-shrink:0; }
  .hdr-search-item-title { font-size:.86rem;font-weight:600;color:var(--ink); }
  .hdr-search-item-sub   { font-size:.75rem;color:var(--ink-muted); }
  .hdr-search-empty { padding:16px;text-align:center;color:var(--ink-muted);font-size:.84rem; }

  /* Мобильное меню */
  .hdr-mobile-nav { display:none;position:fixed;top:72px;left:0;right:0;z-index:197;background:var(--white);border-bottom:1px solid var(--border);padding:10px 20px 14px;box-shadow:var(--shadow); }
  .hdr-mobile-nav.open { display:block; }
  .hdr-mobile-nav ul { list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:3px; }
  .hdr-mobile-nav a { display:block;padding:9px 12px;border-radius:var(--radius-sm);font-size:.88rem;font-weight:500;color:var(--ink-soft);text-decoration:none; }
  .hdr-mobile-nav a:hover { color:var(--emerald);background:var(--emerald-ghost); }

  @media (max-width:960px) {
    .hdr-nav { display:none; }
    .hdr-burger { display:flex; }
    .hdr-right .btn-ghost,
    .hdr-right .btn-outline { display:none; }
  }
  </style>
</head>
<body>

<!-- ── Шапка ── -->
<header class="site-header" id="siteHeader">
  <div class="container">

    <a href="/vetclinic/index.php" class="hdr-logo">
      <svg width="40" height="40" viewBox="0 0 44 44" fill="none" style="flex-shrink:0;display:block;">
        <defs>
          <linearGradient id="sg2" x1="0" y1="0" x2="44" y2="44" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#0d4f3c"/><stop offset="100%" stop-color="#1e8a6a"/>
          </linearGradient>
        </defs>
        <path d="M8 6 L36 6 Q40 6 40 10 L40 26 Q40 38 22 43 Q4 38 4 26 L4 10 Q4 6 8 6 Z" fill="url(#sg2)"/>
        <rect x="18.5" y="14" width="7" height="18" rx="2" fill="white" opacity=".95"/>
        <rect x="13" y="19.5" width="18" height="7" rx="2" fill="white" opacity=".95"/>
        <circle cx="15" cy="9" r="3.5" fill="#c9a84c"/>
        <circle cx="22" cy="6" r="3"   fill="#c9a84c"/>
        <circle cx="29" cy="9" r="3.5" fill="#c9a84c"/>
      </svg>
      <div class="hdr-logo-txt">Вет<strong>Забота</strong><em> клиника</em></div>
    </a>

    <nav class="hdr-nav">
      <ul>
        <li><a href="/vetclinic/index.php"    <?= $currentPage==='index.php'   ?'class="active"':'' ?>>Главная</a></li>
        <li><a href="/vetclinic/about.php"    <?= $currentPage==='about.php'   ?'class="active"':'' ?>>О клинике</a></li>
        <li><a href="/vetclinic/services.php" <?= $currentPage==='services.php'?'class="active"':'' ?>>Услуги</a></li>
        <li><a href="/vetclinic/doctors.php"  <?= $currentPage==='doctors.php' ?'class="active"':'' ?>>Врачи</a></li>
        <li><a href="/vetclinic/prices.php"   <?= $currentPage==='prices.php'  ?'class="active"':'' ?>>Цены</a></li>
        <li><a href="/vetclinic/schedule.php" <?= $currentPage==='schedule.php'?'class="active"':'' ?>>Расписание</a></li>
        <li><a href="/vetclinic/contacts.php" <?= $currentPage==='contacts.php'?'class="active"':'' ?>>Контакты</a></li>
      </ul>
    </nav>

    <div class="hdr-right">

      <!-- Поиск -->
      <button class="hdr-search-btn" id="searchToggle" type="button" aria-label="Поиск">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
      </button>

      <?php if (isUserLoggedIn()): ?>
        <div class="hdr-notif" id="notifBell">
          <div class="hdr-notif-btn" id="notifToggle">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
            </svg>
          </div>
          <span class="hdr-notif-dot" id="notifDot"></span>
          <div class="hdr-notif-drop" id="notifDropdown">
            <div class="hdr-notif-hdr">
              Уведомления
              <a href="/vetclinic/appointments.php" style="font-size:.74rem;color:var(--emerald);font-weight:500;">Все</a>
            </div>
            <div id="notifBody"><div class="hdr-notif-empty">Загрузка...</div></div>
          </div>
        </div>
        <a href="/vetclinic/profile.php" class="btn btn-ghost"><?= icon('user', 16) ?> <?= e(mb_substr($_SESSION['user_name'],0,10)) ?></a>
        <a href="/vetclinic/logout.php"  class="btn btn-outline">Выйти</a>
      <?php else: ?>
        <a href="/vetclinic/login.php"    class="btn btn-ghost">Войти</a>
        <a href="/vetclinic/register.php" class="btn btn-outline">Регистрация</a>
      <?php endif; ?>

      <button class="theme-toggle" id="themeToggle" type="button" aria-label="Сменить тему" title="Сменить тему">
        <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="4"/>
          <path d="M12 2v2"/><path d="M12 20v2"/>
          <path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/>
          <path d="M2 12h2"/><path d="M20 12h2"/>
          <path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
        </svg>
        <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
        </svg>
      </button>
      <a href="/vetclinic/appointment_new.php" class="btn btn-gold"><?= icon('calendar', 16) ?> Записаться</a>
    </div>

    <button class="hdr-burger" id="burgerBtn" type="button" aria-label="Меню">
      <span></span><span></span><span></span>
    </button>

  </div>
</header>

<!-- Поиск — оверлей (ВНЕ header, поэтому не обрезается sticky) -->
<div class="hdr-search-overlay" id="searchOverlay">
  <div class="hdr-search-inner">
    <input type="text" id="siteSearch"
           placeholder="Поиск услуг и врачей..."
           autocomplete="off">
    <span class="hdr-search-ico">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    </span>
    <div class="hdr-search-results" id="searchResults"></div>
  </div>
</div>

<!-- Мобильное меню — тоже вне header -->
<nav class="hdr-mobile-nav" id="mobileNav">
  <ul>
    <li><a href="/vetclinic/index.php">Главная</a></li>
    <li><a href="/vetclinic/about.php">О клинике</a></li>
    <li><a href="/vetclinic/services.php">Услуги</a></li>
    <li><a href="/vetclinic/doctors.php">Врачи</a></li>
    <li><a href="/vetclinic/prices.php">Цены</a></li>
    <li><a href="/vetclinic/schedule.php">Расписание</a></li>
    <li><a href="/vetclinic/contacts.php">Контакты</a></li>
    <?php if (!isUserLoggedIn()): ?>
      <li><a href="/vetclinic/login.php">Войти</a></li>
      <li><a href="/vetclinic/register.php">Регистрация</a></li>
    <?php endif; ?>
  </ul>
</nav>

<main class="site-main">

<script nonce="<?= e($cspNonce) ?>">
// Инициализация поиска — выполняется сразу после загрузки DOM
(function() {
    function initSearch() {
        var btn     = document.getElementById('searchToggle');
        var overlay = document.getElementById('searchOverlay');
        var input   = document.getElementById('siteSearch');
        var results = document.getElementById('searchResults');

        if (!btn || !overlay) return;

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (overlay.style.display === 'block') {
                overlay.style.display = 'none';
            } else {
                overlay.style.display = 'block';
                if (input) { input.value = ''; input.focus(); }
                if (results) { results.style.display = 'none'; results.innerHTML = ''; }
            }
        });

        document.addEventListener('click', function(e) {
            if (overlay.style.display === 'block') {
                if (!overlay.contains(e.target) && !btn.contains(e.target)) {
                    overlay.style.display = 'none';
                }
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') overlay.style.display = 'none';
        });

        if (input && results) {
            var timer = null;
            input.addEventListener('input', function() {
                clearTimeout(timer);
                var q = this.value.trim();
                if (q.length < 2) { results.style.display = 'none'; results.innerHTML = ''; return; }
                timer = setTimeout(function() {
                    fetch('/vetclinic/search.php?q=' + encodeURIComponent(q))
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            showResults(data.results || [], results);
                        }).catch(function() {});
                }, 300);
            });
        }

        function showResults(items, container) {
            container.innerHTML = '';
            if (!items.length) {
                container.innerHTML = '<div class="hdr-search-empty">Ничего не найдено</div>';
                container.style.display = 'block';
                return;
            }
            items.forEach(function(item) {
                var a = document.createElement('a');
                a.href = item.url;
                a.className = 'hdr-search-item';
                var d = document.createElement('div');
                d.textContent = item.title;
                var s = document.createElement('div');
                s.textContent = item.subtitle;
                a.innerHTML = '<div class="hdr-search-item-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></div>'
                    + '<div><div class="hdr-search-item-title">' + d.innerHTML + '</div>'
                    + '<div class="hdr-search-item-sub">' + s.innerHTML + '</div></div>';
                container.appendChild(a);
            });
            container.style.display = 'block';
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSearch);
    } else {
        initSearch();
    }
})();
</script>
