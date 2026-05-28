<?php
/**
 * Bootstrap для PHPUnit-тестов
 * Загружает только те функции, которые не требуют подключения к БД
 */

// Запускаем сессию до любых проверок
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Минимальная реализация функций из functions.php, ──
// ── которые тестируются в изоляции (без БД).           ──

/**
 * Экранирование HTML — защита от XSS.
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Валидация пароля: 8+ символов, заглавная, цифра, спецсимвол.
 */
function validatePassword(string $password): array {
    $errors = [];
    if (mb_strlen($password) < 8) {
        $errors[] = 'Минимум 8 символов.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Минимум 1 заглавная буква.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Минимум 1 цифра.';
    }
    if (!preg_match('/[!@#$%^&*()\-_=+\[\]{};:,.<>\/?]/', $password)) {
        $errors[] = 'Минимум 1 спецсимвол.';
    }
    return $errors;
}

/**
 * Валидация email.
 */
function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Валидация телефона (формат +7 XXX XXX-XX-XX или похожий).
 */
function validatePhone(string $phone): bool {
    $clean = preg_replace('/[\s\-\(\)]/', '', $phone);
    return preg_match('/^\+?[0-9]{10,15}$/', $clean) === 1;
}

/**
 * Генерация CSRF-токена.
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Проверка CSRF-токена через hash_equals (защита от timing-атак).
 */
function checkCsrfToken(string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Проверка заблокирован ли IP по количеству попыток (20 за 15 минут).
 */
function isIpRateLimited(string $ip): bool {
    $key = 'ip_attempts_' . md5($ip);
    if (!isset($_SESSION[$key])) return false;
    $data = $_SESSION[$key];
    if (time() > $data['reset_at']) {
        unset($_SESSION[$key]);
        return false;
    }
    return $data['count'] >= 20;
}

/**
 * Регистрация попытки входа с IP.
 */
function registerIpAttempt(string $ip): void {
    $key = 'ip_attempts_' . md5($ip);
    if (!isset($_SESSION[$key]) || time() > $_SESSION[$key]['reset_at']) {
        $_SESSION[$key] = [
            'count'    => 0,
            'reset_at' => time() + 900,
            'ip'       => $ip,
        ];
    }
    $_SESSION[$key]['count']++;
}

/**
 * Сброс счётчика попыток для IP (после успешного входа).
 */
function resetIpAttempts(string $ip): void {
    $key = 'ip_attempts_' . md5($ip);
    unset($_SESSION[$key]);
}

/**
 * Хеширование токена восстановления пароля.
 */
function hashResetToken(string $rawToken): string {
    return hash('sha256', $rawToken);
}

/**
 * Проверка срока действия токена.
 */
function isTokenExpired(string $expiresAt): bool {
    return (new DateTime()) > (new DateTime($expiresAt));
}
