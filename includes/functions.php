<?php
require_once __DIR__ . '/cache.php';
// =====================================================
// includes/functions.php
// Защиты: XSS, SQL-инъекции, CSRF, Brute-force,
//         Rate limiting по IP, Content Security Policy
// =====================================================

// ── БАЗОВЫЕ УТИЛИТЫ ──────────────────────────────────

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ── НАСТРОЙКИ САЙТА ──────────────────────────────────

function getSetting(mysqli $db, string $key): string {
    $stmt = $db->prepare("SELECT svalue FROM settings WHERE skey = ? LIMIT 1");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (string)$row['svalue'] : '';
}

// ── ДАННЫЕ БД ────────────────────────────────────────

function getActiveServices($db): array {
    return cacheGet('services_active', 300, function() use ($db) {
    $result = $db->query("SELECT * FROM services WHERE is_active = 1 ORDER BY name ASC");
    return $result->fetch_all(MYSQLI_ASSOC);
    });
}

function getActiveDoctors($db): array {
    return cacheGet('doctors_active', 300, function() use ($db) {
    $result = $db->query("SELECT * FROM doctors WHERE is_active = 1 ORDER BY name ASC");
    return $result->fetch_all(MYSQLI_ASSOC);
    });
}

function getAllDoctors(mysqli $db): array {
    $result = $db->query("SELECT * FROM doctors ORDER BY name ASC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// ── ФОРМАТИРОВАНИЕ ───────────────────────────────────

function statusLabel(string $status): string {
    return match($status) {
        'new'       => 'Новая',
        'confirmed' => 'Подтверждена',
        'cancelled' => 'Отменена',
        'completed' => 'Завершена',
        default     => $status,
    };
}

function statusBadgeClass(string $status): string {
    return match($status) {
        'new'       => 'badge-new',
        'confirmed' => 'badge-confirmed',
        'cancelled' => 'badge-cancelled',
        'completed' => 'badge-completed',
        default     => '',
    };
}

function formatDate(string $date): string {
    $months = [
        1=>'января', 2=>'февраля', 3=>'марта',    4=>'апреля',
        5=>'мая',    6=>'июня',    7=>'июля',      8=>'августа',
        9=>'сентября',10=>'октября',11=>'ноября', 12=>'декабря',
    ];
    $ts = strtotime($date);
    return date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function formatTime(string $time): string {
    return substr($time, 0, 5);
}

// ── АВТОРИЗАЦИЯ / СЕССИИ ─────────────────────────────

function isUserLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireUserAuth(): void {
    if (!isUserLoggedIn()) {
        redirect('/vetclinic/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function requireAdminAuth(): void {
    if (!isAdminLoggedIn()) {
        redirect('/vetclinic/admin/login.php');
    }
}

// ── FLASH-СООБЩЕНИЯ ──────────────────────────────────

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function showFlash(): void {
    $flash = getFlash();
    if ($flash) {
        $type = e($flash['type']);
        $msg  = e($flash['message']);
        echo "<div class=\"alert alert-{$type}\">{$msg}</div>";
    }
}

// =====================================================
// 1. ЗАЩИТА ОТ XSS — Content Security Policy
// =====================================================
//
// Вызывается один раз в начале каждой страницы (через header.php).
// Запрещает браузеру выполнять:
//   - inline-скрипты из сторонних источников
//   - загрузку ресурсов с чужих доменов
//   - встроенный JavaScript через data: URL
//
// 'nonce-...' позволяет разрешить только наши inline-скрипты
// с конкретным одноразовым значением.

function generateCspNonce(): string {
    // Генерируем случайный nonce для каждого запроса
    if (!isset($_SESSION['csp_nonce'])) {
        $_SESSION['csp_nonce'] = base64_encode(random_bytes(16));
    }
    return $_SESSION['csp_nonce'];
}

function sendSecurityHeaders(): void {
    $nonce = generateCspNonce();

    // Content Security Policy
    // - default-src 'self'            → по умолчанию только наш домен
    // - script-src 'self' 'nonce-...' → скрипты только наши + с nonce
    // - style-src 'self' fonts.google → стили наши + Google Fonts
    // - font-src fonts.gstatic.com    → шрифты только с Google
    // - img-src 'self' data:          → картинки наши + base64
    // - object-src 'none'             → запрет Flash, плагинов
    // - base-uri 'self'               → запрет подмены <base href>
    // - form-action 'self'            → формы отправляются только на наш домен
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
        "font-src https://fonts.gstatic.com; " .
        "img-src 'self' data: https://images.unsplash.com https://source.unsplash.com; " .
        "frame-src https://www.openstreetmap.org; " .
        "connect-src 'self'; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self';"
    );

    // Запрет встраивания сайта в iframe (защита от Clickjacking)
    header("X-Frame-Options: DENY");

    // Запрет MIME-sniffing браузером
    header("X-Content-Type-Options: nosniff");

    // Включаем встроенный XSS-фильтр старых браузеров
    header("X-XSS-Protection: 1; mode=block");

    // Запрет передачи Referer на внешние сайты
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

// =====================================================
// 2. ЗАЩИТА ОТ CSRF
// =====================================================
//
// CSRF (Cross-Site Request Forgery) — атака, при которой
// вредоносный сайт заставляет браузер жертвы отправить
// запрос на наш сайт (например, сменить пароль).
//
// Защита: в каждую форму вставляем скрытый токен,
// который знает только наш сервер и текущая сессия.
// Злоумышленник не может его угадать или получить
// (политика Same-Origin запрещает читать чужие страницы).

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Выводит скрытое поле CSRF прямо в HTML-форму.
 * Использование: <?= csrfField() ?> внутри <form>
 */
function csrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Проверяет CSRF-токен при обработке POST-запроса.
 * При провале — завершает скрипт с ошибкой 403.
 */
function verifyCsrf(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';

    // hash_equals — защита от timing-атак (сравнение за константное время)
    if (empty($submitted) || empty($expected) || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        die('<h1>403 — Недействительный токен запроса</h1>
             <p>Возможно, форма устарела. <a href="javascript:history.back()">Вернуться назад</a></p>');
    }

    // Обновляем токен после каждого успешного использования
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =====================================================
// 3. RATE LIMITING ПО IP
// =====================================================
//
// Дополняет защиту от brute-force по аккаунту:
// блокирует атаки на несуществующие аккаунты и атаки
// с перебором email-адресов с одного IP-адреса.
//
// Хранение: в сессии PHP (для учебного проекта достаточно).
// В production: Redis или отдельная таблица в БД.

define('IP_MAX_ATTEMPTS', 20);   // максимум попыток с одного IP
define('IP_LOCKOUT_SECS', 900);  // блокировка 15 минут (в секундах)

/**
 * Получает реальный IP пользователя
 */
function getClientIp(): string {
    // Проверяем заголовки прокси (не доверяем им полностью, но используем как fallback)
    $candidates = [
        'HTTP_CF_CONNECTING_IP',   // Cloudflare
        'HTTP_X_REAL_IP',          // Nginx proxy
        'HTTP_X_FORWARDED_FOR',    // стандартный прокси-заголовок
        'REMOTE_ADDR',             // прямое подключение (наиболее надёжно)
    ];
    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            // X-Forwarded-For может содержать цепочку IP через запятую
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Проверяет, не превышен ли лимит запросов с данного IP.
 * Возвращает true если IP заблокирован.
 */
function isIpRateLimited(): bool {
    $ip  = getClientIp();
    $key = 'ip_attempts_' . md5($ip);

    if (!isset($_SESSION[$key])) {
        return false;
    }

    $data = $_SESSION[$key];

    // Сбрасываем устаревшую блокировку
    if (time() > $data['reset_at']) {
        unset($_SESSION[$key]);
        return false;
    }

    return $data['count'] >= IP_MAX_ATTEMPTS;
}

/**
 * Регистрирует неудачную попытку с данного IP.
 */
function registerIpAttempt(): void {
    $ip  = getClientIp();
    $key = 'ip_attempts_' . md5($ip);

    if (!isset($_SESSION[$key]) || time() > $_SESSION[$key]['reset_at']) {
        // Первая попытка или истёк период — начинаем новый отсчёт
        $_SESSION[$key] = [
            'count'    => 1,
            'reset_at' => time() + IP_LOCKOUT_SECS,
            'ip'       => $ip,
        ];
    } else {
        $_SESSION[$key]['count']++;
    }
}

/**
 * Сбрасывает счётчик IP после успешного входа.
 */
function resetIpAttempts(): void {
    $ip  = getClientIp();
    $key = 'ip_attempts_' . md5($ip);
    unset($_SESSION[$key]);
}

/**
 * Возвращает сколько минут осталось до снятия IP-блокировки.
 */
function getIpLockoutMinutesLeft(): int {
    $ip  = getClientIp();
    $key = 'ip_attempts_' . md5($ip);
    if (!isset($_SESSION[$key])) return 0;
    $secs = max(0, $_SESSION[$key]['reset_at'] - time());
    return (int)ceil($secs / 60);
}

// =====================================================
// ВАЛИДАЦИЯ ПАРОЛЯ
// =====================================================

function validatePassword(string $password): array {
    $errors = [];
    if (mb_strlen($password) < 8) {
        $errors[] = 'Пароль должен содержать минимум 8 символов.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Пароль должен содержать минимум одну заглавную букву (A-Z).';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Пароль должен содержать минимум одну цифру.';
    }
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?`~]/', $password)) {
        $errors[] = 'Пароль должен содержать минимум один специальный символ (!@#$%^&* и др.).';
    }
    return $errors;
}

// =====================================================
// BRUTE-FORCE — БЛОКИРОВКА АККАУНТА
// =====================================================

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES',    15);

function isUserLocked(array $user): bool {
    if (empty($user['locked_until'])) return false;
    return new DateTime() < new DateTime($user['locked_until']);
}

function getLockoutMinutesLeft(array $user): int {
    if (empty($user['locked_until'])) return 0;
    $now    = new DateTime();
    $locked = new DateTime($user['locked_until']);
    if ($now >= $locked) return 0;
    return (int)ceil(($locked->getTimestamp() - $now->getTimestamp()) / 60);
}

function registerFailedLogin(mysqli $db, int $userId): void {
    $maxAttempts    = MAX_LOGIN_ATTEMPTS;
    $lockoutMinutes = LOCKOUT_MINUTES;
    $stmt = $db->prepare(
        "UPDATE users
         SET login_attempts = login_attempts + 1,
             locked_until = CASE
                 WHEN login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                 ELSE locked_until
             END
         WHERE id = ?"
    );
    $stmt->bind_param('iii', $maxAttempts, $lockoutMinutes, $userId);
    $stmt->execute();
    $stmt->close();
}

function resetLoginAttempts(mysqli $db, int $userId): void {
    $stmt = $db->prepare(
        "UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

// =====================================================
// ЛОГИРОВАНИЕ ДЕЙСТВИЙ АДМИНИСТРАТОРА
// =====================================================

function logAdminAction(mysqli $db, string $action): void {
    if (empty($_SESSION['admin_id'])) return;
    $adminId = (int)$_SESSION['admin_id'];
    $ip      = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $stmt = $db->prepare(
        "INSERT INTO admin_logs (admin_id, action, ip) VALUES (?, ?, ?)"
    );
    $stmt->bind_param('iss', $adminId, $action, $ip);
    $stmt->execute();
    $stmt->close();
}
