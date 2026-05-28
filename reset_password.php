<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isUserLoggedIn()) redirect('/vetclinic/profile.php');

$errors     = [];
$success    = false;
$tokenValid = false;
$resetRow   = null;
$rawToken   = trim($_GET['token'] ?? '');

if (empty($rawToken)) redirect('/vetclinic/forgot_password.php');

$tokenHash = hash('sha256', $rawToken);
$stmt = $db->prepare(
    "SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.email
     FROM password_resets pr JOIN users u ON pr.user_id = u.id
     WHERE pr.token_hash = ? LIMIT 1"
);
$stmt->bind_param('s', $tokenHash);
$stmt->execute();
$resetRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$resetRow)                                                   $errors[] = 'Ссылка недействительна.';
elseif ($resetRow['used'])                                        $errors[] = 'Эта ссылка уже была использована. Запросите новую.';
elseif (new DateTime() > new DateTime($resetRow['expires_at']))  $errors[] = 'Срок действия ссылки истёк (15 минут). Запросите новую.';
else $tokenValid = true;

if ($tokenValid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    foreach (validatePassword($password) as $pe) $errors[] = $pe;
    if ($password !== $password2) $errors[] = 'Пароли не совпадают.';

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hash, $resetRow['user_id']);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        $stmt->bind_param('i', $resetRow['id']);
        $stmt->execute();
        $stmt->close();

        resetLoginAttempts($db, $resetRow['user_id']);
        $success = true;
    }
}

$pageTitle = 'Новый пароль';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <div class="container">
    <div class="auth-box">
      <div class="auth-header">
        <h1>Новый пароль</h1>
        <p>Придумайте надёжный пароль для вашего аккаунта</p>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success">✅ Пароль успешно изменён!</div>
        <a href="/vetclinic/login.php" class="btn btn-primary btn-full">Войти</a>

      <?php elseif (!$tokenValid): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
        </div>
        <a href="/vetclinic/forgot_password.php" class="btn btn-outline btn-full">Запросить новую ссылку</a>

      <?php else: ?>
        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <p style="color:var(--ink-muted);font-size:.88rem;margin-bottom:20px;">
          Сброс для: <strong><?= e($resetRow['email']) ?></strong>
        </p>

        <form method="POST"
              action="/vetclinic/reset_password.php?token=<?= e(urlencode($rawToken)) ?>"
              novalidate>
          <?= csrfField() ?>

          <div class="form-group">
            <label for="password">Новый пароль *</label>
            <input type="password" id="password" name="password"
                   placeholder="Минимум 8 символов" required>
          </div>
          <div class="form-group">
            <label for="password2">Подтверждение *</label>
            <input type="password" id="password2" name="password2"
                   placeholder="Повторите пароль" required>
          </div>

          <div class="password-requirements">
            <p>Требования к паролю:</p>
            <ul>
              <li id="req-length">Минимум 8 символов</li>
              <li id="req-upper">Минимум 1 заглавная буква (A-Z)</li>
              <li id="req-digit">Минимум 1 цифра (0-9)</li>
              <li id="req-special">Минимум 1 специальный символ</li>
            </ul>
          </div>

          <button type="submit" class="btn btn-primary btn-full">Сохранить новый пароль</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<style nonce="<?= e($cspNonce ?? '') ?>">
.password-requirements{background:var(--emerald-ghost);border:1px solid var(--border);border-radius:var(--radius);padding:14px 18px;margin-bottom:18px;font-size:.84rem}
.password-requirements p{font-weight:700;color:var(--ink-soft);margin-bottom:8px;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}
.password-requirements ul{list-style:none;display:flex;flex-direction:column;gap:5px}
.password-requirements li{color:var(--ink-muted);display:flex;align-items:center;gap:8px}
.password-requirements li::before{content:'○'}
.password-requirements li.ok{color:var(--emerald)}
.password-requirements li.ok::before{content:'●'}
</style>
<script nonce="<?= e($cspNonce ?? '') ?>">
const p = document.getElementById('password');
if(p) p.addEventListener('input', function(){
    const v=this.value,c=(id,ok)=>document.getElementById(id).classList.toggle('ok',ok);
    c('req-length',v.length>=8); c('req-upper',/[A-Z]/.test(v));
    c('req-digit',/[0-9]/.test(v)); c('req-special',/[!@#$%^&*()\-_=+\[\]{};':"\\|,.<>\/?`~]/.test(v));
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
