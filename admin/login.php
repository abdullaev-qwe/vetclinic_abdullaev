<?php
// admin/login.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (isAdminLoggedIn()) redirect('/vetclinic/admin/index.php');

$errors   = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']      ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = 'Введите логин и пароль.';
    }

    if (empty($errors)) {
        $stmt = $db->prepare(
            "SELECT id, username, password FROM admins WHERE username = ? LIMIT 1"
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            redirect('/vetclinic/admin/index.php');
        } else {
            $errors[] = 'Неверный логин или пароль.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Вход — Панель управления ВетЗабота</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="/vetclinic/assets/css/admin.css">
</head>
<body class="admin-login-page">

<div class="admin-login-box">

  <!-- Логотип -->
  <div class="admin-login-logo">
    <svg width="56" height="56" viewBox="0 0 44 44" fill="none"
         xmlns="http://www.w3.org/2000/svg" style="display:block;margin:0 auto 16px;">
      <defs>
        <linearGradient id="lg" x1="0" y1="0" x2="44" y2="44" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#0d4f3c"/>
          <stop offset="100%" stop-color="#1e8a6a"/>
        </linearGradient>
      </defs>
      <path d="M8 6 L36 6 Q40 6 40 10 L40 26 Q40 38 22 43 Q4 38 4 26 L4 10 Q4 6 8 6 Z"
            fill="url(#lg)"/>
      <rect x="18.5" y="14" width="7" height="18" rx="2" fill="white" opacity="0.95"/>
      <rect x="13" y="19.5" width="18" height="7" rx="2" fill="white" opacity="0.95"/>
      <circle cx="15" cy="9"  r="3.5" fill="#c9a84c"/>
      <circle cx="22" cy="6"  r="3"   fill="#c9a84c"/>
      <circle cx="29" cy="9"  r="3.5" fill="#c9a84c"/>
    </svg>
    <h1>ВетЗабота · Админ</h1>
    <p>Панель управления клиникой</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="/vetclinic/admin/login.php">

    <div class="form-group">
      <label for="username">Логин администратора</label>
      <input type="text" id="username" name="username"
             value="<?= e($username) ?>"
             placeholder="admin"
             required autofocus>
    </div>

    <div class="form-group">
      <label for="password">Пароль</label>
      <input type="password" id="password" name="password"
             placeholder="••••••••" required>
    </div>

    <button type="submit" class="btn btn-primary btn-full">
      Войти в панель
    </button>
  </form>

  <div class="admin-login-hint">
    <p>Логин: <code>admin</code> &nbsp;/&nbsp; Пароль: <code>admin123</code></p>
    <a href="/vetclinic/index.php">← Вернуться на сайт</a>
  </div>

</div>

</body>
</html>
