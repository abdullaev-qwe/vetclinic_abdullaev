<?php
// =====================================================
// login.php — Вход: CSRF + Brute-force + IP Rate Limit
// =====================================================
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isUserLoggedIn()) redirect('/vetclinic/profile.php');

$errors   = [];
$email    = '';
$redirect = $_GET['redirect'] ?? '/vetclinic/profile.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Проверка CSRF
    verifyCsrf();

    // 2. Проверка Rate Limit по IP (до проверки пароля)
    if (isIpRateLimited()) {
        $mins = getIpLockoutMinutesLeft();
        $errors[] = "Слишком много попыток входа с вашего IP-адреса. "
                  . "Повторите через {$mins} мин.";
    }

    if (empty($errors)) {
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password']      ?? '';
        $redirect = $_POST['redirect']      ?? '/vetclinic/profile.php';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email.';
        }
        if (empty($password)) {
            $errors[] = 'Введите пароль.';
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare(
            "SELECT id, name, email, password, login_attempts, locked_until
             FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            if (isUserLocked($user)) {
                // Аккаунт заблокирован
                $mins = getLockoutMinutesLeft($user);
                $errors[] = "Аккаунт временно заблокирован. Повторите через {$mins} мин.";
                registerIpAttempt(); // фиксируем и по IP

            } elseif (password_verify($password, $user['password'])) {
                // ── Успешный вход ──
                // Между SELECT-ом и этой точкой выполнялся bcrypt
                // (password_verify ~200-500 мс). За это время MySQL мог
                // оборвать соединение, поэтому проверяем и переподключаемся.
                $db = dbEnsureAlive($db);

                resetLoginAttempts($db, $user['id']);
                resetIpAttempts();
                session_regenerate_id(true);
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                setFlash('success', 'Добро пожаловать, ' . $user['name'] . '!');
                redirect(str_starts_with($redirect, '/vetclinic/') ? $redirect : '/vetclinic/profile.php');

            } else {
                // ── Неверный пароль ──
                registerFailedLogin($db, $user['id']);
                registerIpAttempt();

                $stmt2 = $db->prepare("SELECT login_attempts FROM users WHERE id = ?");
                $stmt2->bind_param('i', $user['id']);
                $stmt2->execute();
                $upd  = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();

                $left = MAX_LOGIN_ATTEMPTS - (int)$upd['login_attempts'];
                if ($left <= 0) {
                    $errors[] = 'Аккаунт заблокирован на ' . LOCKOUT_MINUTES . ' минут.';
                } else {
                    $errors[] = 'Неверный email или пароль. Осталось попыток: ' . max(0, $left) . '.';
                }
            }
        } else {
            // Пользователь не найден — не раскрываем деталей
            registerIpAttempt();
            $errors[] = 'Неверный email или пароль.';
        }
    }
}

$pageTitle = 'Вход';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <div class="container">
    <div class="auth-box">
      <div class="auth-header">
        <h1>Вход в аккаунт</h1>
        <p>Войдите, чтобы управлять записями на приём</p>
      </div>

      <?php showFlash(); ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="/vetclinic/login.php" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email"
                 value="<?= e($email) ?>"
                 placeholder="ivan@example.com" required autofocus>
        </div>

        <div class="form-group">
          <label for="password">Пароль</label>
          <input type="password" id="password" name="password"
                 placeholder="Ваш пароль" required>
        </div>

        <button type="submit" class="btn btn-primary btn-full">Войти</button>
      </form>

      <div class="auth-footer">
        <p>Забыли пароль? <a href="/vetclinic/forgot_password.php">Восстановить</a></p>
        <p style="margin-top:8px;">Нет аккаунта? <a href="/vetclinic/register.php">Зарегистрироваться</a></p>
        <p class="hint">Тест: anna@example.com / Password1!</p>
      </div>

      <!-- Разделитель -->
      <div style="display:flex;align-items:center;gap:12px;margin:24px 0 0;">
        <div style="flex:1;height:1px;background:var(--border);"></div>
        <span style="font-size:.75rem;color:var(--ink-muted);text-transform:uppercase;letter-spacing:.08em;">или</span>
        <div style="flex:1;height:1px;background:var(--border);"></div>
      </div>

      <!-- Кнопка входа для администратора -->
      <a href="/vetclinic/admin/login.php"
         style="display:flex;align-items:center;justify-content:center;gap:10px;
                margin-top:16px;padding:11px 24px;
                border-radius:var(--radius-sm);border:1.5px solid var(--border);
                background:transparent;color:var(--ink-soft);
                font-family:var(--font-body);font-size:.82rem;font-weight:600;
                letter-spacing:.04em;text-transform:uppercase;
                text-decoration:none;transition:var(--t-fast);"
         onmouseover="this.style.borderColor='var(--emerald)';this.style.color='var(--emerald)';this.style.background='var(--emerald-ghost)'"
         onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--ink-soft)';this.style.background='transparent'">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
          <path d="m9 12 2 2 4-4"/>
        </svg>
        Вход для администратора
      </a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
