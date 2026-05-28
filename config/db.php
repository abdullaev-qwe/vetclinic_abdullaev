<?php
// =====================================================
// config/db.php — Подключение к базе данных
// =====================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // стандартный пользователь XAMPP
define('DB_PASS', '');           // стандартный пароль XAMPP (пустой)
define('DB_NAME', 'vetclinic');
define('DB_CHARSET', 'utf8mb4');

/**
 * Создаёт свежее подключение к БД с настройками таймаутов.
 * Используется при первом подключении и при необходимости
 * восстановить соединение после ошибки «MySQL server has gone away».
 */
function dbConnect(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die('Ошибка подключения к базе данных: ' . $conn->connect_error);
    }
    $conn->set_charset(DB_CHARSET);
    // Поднимаем таймауты, чтобы соединение не умирало между запросами
    // в рамках одного HTTP-запроса (особенно после bcrypt password_verify).
    @$conn->query("SET SESSION wait_timeout = 28800");
    @$conn->query("SET SESSION interactive_timeout = 28800");
    return $conn;
}

/**
 * Проверяет, что соединение с MySQL живо. Если оборвано —
 * переподключается. Возвращает живое соединение (тот же объект
 * либо новый), чтобы код вызова мог его подменить.
 *
 * Использование:
 *     $db = dbEnsureAlive($db);
 *     // далее можно безопасно делать $db->prepare(...)
 */
function dbEnsureAlive(mysqli $db): mysqli {
    try {
        // Пинг — самый дешёвый способ проверить живость соединения.
        // В PHP 8.4+ ping() помечен deprecated, но пока работает; завернём в @.
        if (@$db->ping()) {
            return $db;
        }
    } catch (\Throwable $e) {
        // ping() выбросил исключение — соединение точно мертво
    }
    // Соединение умерло — переподключаемся.
    @$db->close();
    return dbConnect();
}

$db = dbConnect();
