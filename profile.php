<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/avatar.php';

requireUserAuth();

$userId         = (int)$_SESSION['user_id'];
$errors         = [];
$errorsPassword = [];
$successInfo    = false;
$successPass    = false;
$activeTab      = 'info';

$stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Обновление данных ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_info') {
    verifyCsrf();
    $activeTab = 'info';
    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city  = trim($_POST['city']  ?? '');
    if (mb_strlen($name) < 2) $errors[] = 'Введите имя (минимум 2 символа).';
    if (empty($city))          $errors[] = 'Введите город.';
    if (empty($errors)) {
        $stmt = $db->prepare("UPDATE users SET name=?, phone=?, city=? WHERE id=?");
        $stmt->bind_param('sssi', $name, $phone, $city, $userId);
        $stmt->execute();
        $stmt->close();
        $_SESSION['user_name'] = $name;
        $user['name'] = $name; $user['phone'] = $phone; $user['city'] = $city;
        $successInfo = true;
    }
}

// ── Смена пароля ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    verifyCsrf();
    $activeTab = 'password';
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password']     ?? '';
    $newPass2    = $_POST['new_password2']    ?? '';
    if (!password_verify($currentPass, $user['password'])) {
        $errorsPassword[] = 'Неверный текущий пароль.';
    }
    foreach (validatePassword($newPass) as $pe) $errorsPassword[] = $pe;
    if ($newPass !== $newPass2) $errorsPassword[] = 'Новые пароли не совпадают.';
    if (empty($errorsPassword)) {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param('si', $hash, $userId);
        $stmt->execute();
        $stmt->close();
        $successPass = true;
    }
}

// Ближайшие записи
$stmt = $db->prepare(
    "SELECT * FROM view_user_appointments
     WHERE user_id=? AND status IN ('new','confirmed') AND appointment_date >= CURDATE()
     ORDER BY appointment_date ASC, appointment_time ASC LIMIT 3"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$upcoming = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Статистика
$stmt = $db->prepare(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status IN ('new','confirmed') THEN 1 ELSE 0 END) AS active
     FROM appointments WHERE user_id=?"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$pageTitle = 'Личный кабинет';
require_once __DIR__ . '/includes/header.php';
?>

<section class="profile-section">
  <div class="container">
    <?php showFlash(); ?>

    <!-- Шапка профиля -->
    <div class="profile-header">
      <div class="profile-avatar">
        <?= renderAvatar($user['name'], $user['avatar'] ?? null, 80) ?>
      </div>
      <div class="profile-info">
        <h1><?= e($user['name']) ?></h1>
        <p><?= e($user['email']) ?></p>
        <?php if ($user['phone']): ?><p><?= e($user['phone']) ?></p><?php endif; ?>
        <?php if (!empty($user['city'])): ?><p style="display:inline-flex;align-items:center;gap:6px;"><?= icon('map-pin', 14) ?> <?= e($user['city']) ?></p><?php endif; ?>
        <p class="profile-since">Аккаунт создан: <?= formatDate($user['created_at']) ?></p>
      </div>
    </div>

    <!-- Статистика -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-number"><?= (int)$stats['total'] ?></div>
        <div class="stat-label">Всего записей</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= (int)$stats['active'] ?></div>
        <div class="stat-label">Активных</div>
      </div>
      <div class="stat-card">
        <div class="stat-number"><?= (int)$stats['completed'] ?></div>
        <div class="stat-label">Завершённых</div>
      </div>
    </div>

    <!-- Вкладки: используем data-tab вместо onclick (совместимо с CSP) -->
    <div class="profile-tabs" id="profileTabs">
      <button class="profile-tab" data-tab="info">Мои данные</button>
      <button class="profile-tab" data-tab="password">Смена пароля</button>
      <button class="profile-tab" data-tab="appointments">Записи</button>
    </div>

    <!-- Вкладка: Мои данные -->
    <div id="tab-info" class="profile-tab-content">
      <div class="profile-section-block">
        <div class="section-title-row"><h2>Контактная информация</h2></div>
        <?php if ($successInfo): ?>
          <div class="alert alert-success">✅ Данные успешно обновлены.</div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>
        
<!-- ═══ АВАТАР ═══ -->
<?php $avatarFilename = $user['avatar'] ?? null; ?>
<div class="avatar-section">
  <div class="avatar-preview">
    <?= renderAvatar($user['name'], $avatarFilename, 96) ?>
  </div>
  <div class="avatar-info">
    <h3>Ваш аватар</h3>
    <p>JPG, PNG или WebP. Макс. размер: 2 МБ. Минимум 64×64 пикселей.</p>
    <div class="avatar-actions">
      <form method="POST" action="/vetclinic/avatar_upload.php" enctype="multipart/form-data" class="avatar-upload-form">
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
        <input type="hidden" name="action" value="upload">
        <label class="avatar-upload-btn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
          <?= $avatarFilename ? 'Сменить' : 'Загрузить' ?>
          <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-auto-submit>
        </label>
      </form>

      <?php if ($avatarFilename): ?>
        <form method="POST" action="/vetclinic/avatar_upload.php" class="avatar-delete-form" onsubmit="return confirm('Удалить аватар?');">
          <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
          <input type="hidden" name="action" value="delete">
          <button type="submit" class="avatar-delete-btn">Удалить</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<!-- ═══ /АВАТАР ═══ -->

<form method="POST" action="/vetclinic/profile.php" novalidate>
          <?= csrfField() ?>
          <input type="hidden" name="action" value="update_info">
          <div class="form-row">
            <div class="form-group">
              <label>Email (не изменяется)</label>
              <input type="email" value="<?= e($user['email']) ?>" disabled
                     style="opacity:.6;cursor:not-allowed;">
            </div>
            <div class="form-group">
              <label for="prof-name">Имя и фамилия *</label>
              <input type="text" id="prof-name" name="name"
                     value="<?= e($user['name']) ?>" required maxlength="100">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="prof-phone">Телефон</label>
              <input type="tel" id="prof-phone" name="phone"
                     value="<?= e($user['phone']) ?>"
                     placeholder="+7-900-000-00-00" maxlength="20">
            </div>
            <div class="form-group">
              <label for="prof-city">Город *</label>
              <input type="text" id="prof-city" name="city"
                     value="<?= e($user['city'] ?? '') ?>"
                     placeholder="Москва" required maxlength="100">
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Сохранить изменения</button>
        </form>
      </div>
    </div>

    <!-- Вкладка: Смена пароля -->
    <div id="tab-password" class="profile-tab-content">
      <div class="profile-section-block">
        <div class="section-title-row"><h2>Смена пароля</h2></div>
        <?php if ($successPass): ?>
          <div class="alert alert-success">✅ Пароль успешно изменён.</div>
        <?php endif; ?>
        <?php if (!empty($errorsPassword)): ?>
          <div class="alert alert-danger">
            <ul><?php foreach ($errorsPassword as $ep): ?><li><?= e($ep) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>
        <form method="POST" action="/vetclinic/profile.php" novalidate>
          <?= csrfField() ?>
          <input type="hidden" name="action" value="change_password">
          <div class="form-group">
            <label for="current_password">Текущий пароль *</label>
            <input type="password" id="current_password" name="current_password"
                   placeholder="Введите текущий пароль" required>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="new_password">Новый пароль *</label>
              <input type="password" id="new_password" name="new_password"
                     placeholder="Новый пароль" required>
            </div>
            <div class="form-group">
              <label for="new_password2">Подтверждение *</label>
              <input type="password" id="new_password2" name="new_password2"
                     placeholder="Повторите новый пароль" required>
            </div>
          </div>
          <div class="password-requirements">
            <p>Требования к паролю:</p>
            <ul>
              <li id="preq-length">Минимум 8 символов</li>
              <li id="preq-upper">Минимум 1 заглавная буква (A-Z)</li>
              <li id="preq-digit">Минимум 1 цифра (0-9)</li>
              <li id="preq-special">Минимум 1 специальный символ</li>
            </ul>
          </div>
          <button type="submit" class="btn btn-primary">Изменить пароль</button>
        </form>
      </div>
    </div>

    <!-- Вкладка: Записи -->
    <div id="tab-appointments" class="profile-tab-content">
      <div class="profile-section-block">
        <div class="section-title-row">
          <h2>Ближайшие записи</h2>
          <a href="/vetclinic/appointments.php" class="btn btn-outline btn-sm">Все записи</a>
        </div>
        <?php if (empty($upcoming)): ?>
          <div class="empty-state">
            <p>Нет предстоящих записей.</p>
            <a href="/vetclinic/appointment_new.php" class="btn btn-primary">Записаться</a>
          </div>
        <?php else: ?>
          <div class="appointments-list">
            <?php foreach ($upcoming as $appt): ?>
              <div class="appointment-card">
                <div class="appt-date">
                  <span class="appt-day"><?= date('d', strtotime($appt['appointment_date'])) ?></span>
                  <span class="appt-month"><?= formatDate($appt['appointment_date']) ?></span>
                  <span class="appt-time"><?= formatTime($appt['appointment_time']) ?></span>
                </div>
                <div class="appt-info">
                  <h3><?= e($appt['service_name']) ?></h3>
                  <p>Врач: <?= e($appt['doctor_name']) ?></p>
                  <p>Питомец: <?= e($appt['pet_name']) ?> (<?= e($appt['pet_type']) ?>)</p>
                </div>
                <div class="appt-status">
                  <span class="badge <?= statusBadgeClass($appt['status']) ?>">
                    <?= statusLabel($appt['status']) ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="quick-actions">
      <a href="/vetclinic/appointment_new.php" class="btn btn-gold">+ Записаться</a>
      <a href="/vetclinic/appointments.php"    class="btn btn-outline">История записей</a>
      <a href="/vetclinic/logout.php"          class="btn btn-ghost">Выйти</a>
    </div>
  </div>
</section>

<style nonce="<?= e($cspNonce ?? '') ?>">
/* Аватар в шапке профиля — корректное отображение renderAvatar() */
.profile-avatar {
  background: transparent !important;
  padding: 0 !important;
  display: flex !important;
  align-items: center;
  justify-content: center;
}
.profile-avatar img,
.profile-avatar > div {
  width: 80px !important;
  height: 80px !important;
  border-radius: 50% !important;
  border: 3px solid var(--gold, #c9a84c);
  box-shadow: 0 4px 14px rgba(0,0,0,0.12);
  font-size: 32px !important;
}

/* Вкладки */
.profile-tabs {
  display: flex;
  gap: 4px;
  margin-bottom: 20px;
  background: var(--white);
  border-radius: var(--radius-lg);
  padding: 6px;
  border: 1px solid var(--border);
  box-shadow: var(--shadow-xs);
}
.profile-tab {
  flex: 1;
  padding: 11px 16px;
  border: none;
  border-radius: var(--radius);
  background: transparent;
  font-family: var(--font-body);
  font-size: .85rem;
  font-weight: 600;
  color: var(--ink-muted);
  cursor: pointer;
  transition: background .18s, color .18s;
  letter-spacing: .03em;
}
.profile-tab:hover  { background: var(--emerald-ghost); color: var(--emerald); }
.profile-tab.active { background: var(--emerald); color: #fff; }

/* Содержимое вкладок — скрыто по умолчанию через CSS */
.profile-tab-content         { display: none; }
.profile-tab-content.visible { display: block; }

/* Требования к паролю */
.password-requirements {
  background: var(--emerald-ghost);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px 18px;
  margin-bottom: 18px;
  font-size: .84rem;
}
.password-requirements p {
  font-weight: 700; color: var(--ink-soft); margin-bottom: 8px;
  font-size: .78rem; text-transform: uppercase; letter-spacing: .06em;
}
.password-requirements ul { list-style: none; display: flex; flex-direction: column; gap: 5px; }
.password-requirements li { color: var(--ink-muted); display: flex; align-items: center; gap: 8px; }
.password-requirements li::before { content: '○'; flex-shrink: 0; }
.password-requirements li.ok { color: var(--emerald); }
.password-requirements li.ok::before { content: '●'; }
</style>

<?php
// Передаём активную вкладку из PHP в JS через data-атрибут тега body
// Это безопасно с CSP — никакого inline JS с данными
$activeTabJson = json_encode($activeTab);
?>
<div id="activeTabData" data-tab="<?= e($activeTab) ?>" style="display:none;"></div>

<script nonce="<?= e($cspNonce ?? '') ?>">
(function() {
    // ── Переключение вкладок ──────────────────────────────
    var TABS = ['info', 'password', 'appointments'];

    function switchTab(name) {
        // Показываем/скрываем содержимое
        TABS.forEach(function(t) {
            var el = document.getElementById('tab-' + t);
            if (el) {
                el.classList.toggle('visible', t === name);
            }
        });
        // Активная кнопка
        document.querySelectorAll('.profile-tab').forEach(function(btn) {
            btn.classList.toggle('active', btn.getAttribute('data-tab') === name);
        });
    }

    // Читаем активную вкладку из data-атрибута (не из inline JS)
    var dataEl  = document.getElementById('activeTabData');
    var initial = dataEl ? dataEl.getAttribute('data-tab') : 'info';
    switchTab(initial);

    // Вешаем обработчики на кнопки через addEventListener (не onclick)
    document.querySelectorAll('.profile-tab').forEach(function(btn) {
        btn.addEventListener('click', function() {
            switchTab(this.getAttribute('data-tab'));
        });
    });

    // ── Живая проверка требований нового пароля ──────────
    var np = document.getElementById('new_password');
    if (np) {
        np.addEventListener('input', function() {
            var v = this.value;
            function check(id, cond) {
                var el = document.getElementById(id);
                if (el) el.classList.toggle('ok', cond);
            }
            check('preq-length',  v.length >= 8);
            check('preq-upper',   /[A-Z]/.test(v));
            check('preq-digit',   /[0-9]/.test(v));
            check('preq-special', /[!@#$%^&*()\-_=+\[\]{};':"\\|,.<>\/?`~]/.test(v));
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
