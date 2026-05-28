<?php
// =====================================================
// logout.php — Выход пользователя
// =====================================================
session_start();
require_once __DIR__ . '/includes/functions.php';

// Уничтожаем сессию
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

redirect('/vetclinic/login.php');
