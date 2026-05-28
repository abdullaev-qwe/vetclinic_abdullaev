<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

if (isUserLoggedIn()) redirect('/vetclinic/profile.php');

$errors = [];
$form   = ['name'=>'','email'=>'','phone'=>'','city'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $form['name']  = trim($_POST['name']   ?? '');
    $form['email'] = trim($_POST['email']  ?? '');
    $form['phone'] = trim($_POST['phone']  ?? '');
    $form['city']  = trim($_POST['city']   ?? '');
    $password      = $_POST['password']    ?? '';
    $password2     = $_POST['password2']   ?? '';

    if (mb_strlen($form['name']) < 2)                        $errors[] = 'Введите имя (минимум 2 символа).';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL))  $errors[] = 'Введите корректный email.';
    if (empty($form['city']))                                 $errors[] = 'Введите ваш город.';

    foreach (validatePassword($password) as $pe) $errors[] = $pe;
    if ($password !== $password2) $errors[] = 'Пароли не совпадают.';

    if (empty($errors)) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $form['email']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $errors[] = 'Пользователь с таким email уже зарегистрирован.';
        $stmt->close();
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare(
            "INSERT INTO users (name, email, phone, city, password) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sssss', $form['name'], $form['email'], $form['phone'], $form['city'], $hash);
        if ($stmt->execute()) {
            $userId = $stmt->insert_id;
            $stmt->close();
            session_regenerate_id(true);
            $_SESSION['user_id']    = $userId;
            $_SESSION['user_name']  = $form['name'];
            $_SESSION['user_email'] = $form['email'];
            setFlash('success', 'Регистрация прошла успешно! Добро пожаловать, ' . $form['name'] . '!');
            redirect('/vetclinic/profile.php');
        } else {
            $errors[] = 'Ошибка при регистрации. Попробуйте ещё раз.';
            $stmt->close();
        }
    }
}

$pageTitle = 'Регистрация';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
  <div class="container">
    <div class="auth-box form-box--wide" style="max-width:580px;">
      <div class="auth-header">
        <h1>Регистрация</h1>
        <p>Создайте аккаунт, чтобы записываться на приём онлайн</p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="/vetclinic/register.php" novalidate>
        <?= csrfField() ?>

        <div class="form-row">
          <div class="form-group">
            <label for="name">Имя и фамилия *</label>
            <input type="text" id="name" name="name" value="<?= e($form['name']) ?>"
                   placeholder="Иван Петров" required maxlength="100">
          </div>
          <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" value="<?= e($form['email']) ?>"
                   placeholder="ivan@example.com" required maxlength="150">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="phone">Телефон</label>
            <input type="tel" id="phone" name="phone" value="<?= e($form['phone']) ?>"
                   placeholder="+7-900-000-00-00" maxlength="20">
          </div>
          <div class="form-group">
            <label for="city">Город *</label>
            <input type="text" id="city" name="city" value="<?= e($form['city']) ?>"
                   placeholder="Москва" required maxlength="100">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="password">Пароль *</label>
            <input type="password" id="password" name="password"
                   placeholder="Минимум 8 символов" required>
          </div>
          <div class="form-group">
            <label for="password2">Подтверждение пароля *</label>
            <input type="password" id="password2" name="password2"
                   placeholder="Повторите пароль" required>
          </div>
        </div>

        <div class="password-requirements">
          <p>Требования к паролю:</p>
          <ul>
            <li id="req-length">Минимум 8 символов</li>
            <li id="req-upper">Минимум 1 заглавная буква (A-Z)</li>
            <li id="req-digit">Минимум 1 цифра (0-9)</li>
            <li id="req-special">Минимум 1 специальный символ (!@#$%^&*...)</li>
          </ul>
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;">
          Создать аккаунт
        </button>
      </form>

      <div class="auth-footer">
        <p>Уже есть аккаунт? <a href="/vetclinic/login.php">Войти</a></p>
      </div>
    </div>
  </div>
</section>

<style nonce="<?= e($cspNonce ?? '') ?>">
.password-requirements{background:var(--emerald-ghost);border:1px solid var(--border);border-radius:var(--radius);padding:14px 18px;margin-bottom:18px;font-size:.84rem}
.password-requirements p{font-weight:700;color:var(--ink-soft);margin-bottom:8px;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}
.password-requirements ul{list-style:none;display:flex;flex-direction:column;gap:5px}
.password-requirements li{color:var(--ink-muted);display:flex;align-items:center;gap:8px}
.password-requirements li::before{content:'○';font-size:.9rem;flex-shrink:0}
.password-requirements li.ok{color:var(--emerald)}
.password-requirements li.ok::before{content:'●'}
</style>

<script nonce="<?= e($cspNonce ?? '') ?>">
const pwdInput = document.getElementById('password');
if (pwdInput) {
    pwdInput.addEventListener('input', function() {
        const v = this.value;
        const check = (id, cond) => document.getElementById(id).classList.toggle('ok', cond);
        check('req-length',  v.length >= 8);
        check('req-upper',   /[A-Z]/.test(v));
        check('req-digit',   /[0-9]/.test(v));
        check('req-special', /[!@#$%^&*()\-_=+\[\]{};':"\\|,.<>\/?`~]/.test(v));
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
