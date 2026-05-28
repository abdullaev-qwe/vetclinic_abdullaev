<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isUserLoggedIn()) redirect('/vetclinic/profile.php');

$errors  = [];
$success = false;
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Введите корректный email.';
    }

    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $stmt->close();

            $rawToken  = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = date('Y-m-d H:i:s', time() + 15 * 60);

            $stmt = $db->prepare(
                "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)"
            );
            $stmt->bind_param('iss', $user['id'], $tokenHash, $expiresAt);
            $stmt->execute();
            $stmt->close();

            $resetLink = 'http://localhost/vetclinic/reset_password.php?token=' . $rawToken;
            $_SESSION['demo_reset_link'] = $resetLink;
        }
        $success = true;
    }
}

$pageTitle = 'Восстановление пароля';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <div class="container">
    <div class="auth-box">
      <div class="auth-header">
        <h1>Восстановление пароля</h1>
        <p>Введите email, указанный при регистрации</p>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success">
          Если аккаунт с таким email существует, ссылка для сброса пароля создана.
          Ссылка действительна <strong>15 минут</strong>.
        </div>
        <?php if (isset($_SESSION['demo_reset_link'])): ?>
          <div class="alert alert-info" style="word-break:break-all;">
            <strong>🎓 Учебный режим — ссылка для сброса:</strong><br><br>
            <a href="<?= e($_SESSION['demo_reset_link']) ?>"><?= e($_SESSION['demo_reset_link']) ?></a>
          </div>
          <?php unset($_SESSION['demo_reset_link']); ?>
        <?php endif; ?>
        <a href="/vetclinic/login.php" class="btn btn-outline btn-full">Вернуться ко входу</a>
      <?php else: ?>
        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <form method="POST" action="/vetclinic/forgot_password.php" novalidate>
          <?= csrfField() ?>
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>"
                   placeholder="ivan@example.com" required autofocus>
          </div>
          <button type="submit" class="btn btn-primary btn-full">Отправить ссылку для сброса</button>
        </form>
        <div class="auth-footer">
          <p>Вспомнили пароль? <a href="/vetclinic/login.php">Войти</a></p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
