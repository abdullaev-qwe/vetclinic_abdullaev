<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/appointment_form.php';

requireUserAuth();

$userId = (int)$_SESSION['user_id'];
$appointmentId = (int)($_GET['id'] ?? 0);

if ($appointmentId <= 0) {
    setFlash('danger', 'Запись не найдена');
    redirect('/vetclinic/appointments.php');
}

$appointment = getAppointmentForUser($db, $appointmentId, $userId);
if (!$appointment) {
    setFlash('danger', 'Запись не найдена или принадлежит другому пользователю');
    redirect('/vetclinic/appointments.php');
}

// Существующая анкета (если редактируем)
$form = getFormByAppointment($db, $appointmentId);

$pageTitle = $form ? 'Редактирование анкеты' : 'Анкета перед приёмом';

$pageError  = '';
$errors     = [];
$justSaved  = ($_GET['saved'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Универсальная проверка CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';
    $csrfValid = false;

    if (function_exists('checkCsrfToken')) {
        $csrfValid = checkCsrfToken($csrfToken);
    } elseif (function_exists('verifyCsrf')) {
        try { verifyCsrf(); $csrfValid = true; } catch (\Throwable $e) { $csrfValid = false; }
    } else {
        $csrfValid = !empty($_SESSION['csrf_token'])
                  && hash_equals($_SESSION['csrf_token'], $csrfToken);
    }

    if (!$csrfValid) {
        $pageError = 'Ошибка безопасности. Попробуйте снова.';
    } else {
        $result = saveAppointmentForm($db, $appointmentId, $_POST, $_FILES['attachment'] ?? null);
        if ($result['ok']) {
            setFlash('success', '✅ Анкета сохранена. Спасибо!');
            redirect('/vetclinic/appointment_form.php?id=' . $appointmentId . '&saved=1');
        } else {
            $errors = $result['errors'] ?? ['Не удалось сохранить'];
        }
    }
    // Перезагружаем форму с введёнными данными
    $form = array_merge($form ?: [], [
        'complaint'      => $_POST['complaint']      ?? '',
        'symptoms'       => implode(',', (array)($_POST['symptoms'] ?? [])),
        'symptoms_other' => $_POST['symptoms_other'] ?? '',
        'pet_age_value'  => $_POST['pet_age_value']  ?? '',
        'pet_age_unit'   => $_POST['pet_age_unit']   ?? 'years',
        'urgency'        => $_POST['urgency']        ?? 'planned',
        'has_tests'      => !empty($_POST['has_tests']) ? 1 : 0,
    ]);
}

// Существующие выбранные симптомы (для отрисовки чекбоксов)
$selectedSymptoms = [];
if ($form && !empty($form['symptoms'])) {
    $selectedSymptoms = explode(',', $form['symptoms']);
}

// CSRF токен
$csrfToken = function_exists('generateCsrfToken') ? generateCsrfToken() : ($_SESSION['csrf_token'] ?? '');

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
  <div class="container">
    <h1><?= $form ? '📋 Редактирование анкеты' : '📋 Анкета перед приёмом' ?></h1>
    <p>Помогите врачу подготовиться — расскажите о состоянии питомца</p>
  </div>
</div>

<section class="page-section">
  <div class="container appt-form-container">

    <?php showFlash(); ?>

    <!-- Информация о записи -->
    <div class="appt-info-card">
      <div class="appt-info-icon"><?= icon('calendar', 22) ?></div>
      <div>
        <strong>Запись на приём:</strong><br>
        <?= formatDate($appointment['appointment_date']) ?>,
        <?= formatTime($appointment['appointment_time']) ?> ·
        <?= e($appointment['doctor_name']) ?> ·
        <?= e($appointment['service_name']) ?>
      </div>
    </div>

    <?php if ($pageError): ?>
      <div class="alert alert-danger"><?= e($pageError) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <strong>Исправьте ошибки:</strong>
        <ul style="margin-top:6px;padding-left:20px;">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="/vetclinic/appointment_form.php?id=<?= $appointmentId ?>"
          enctype="multipart/form-data" class="appt-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

      <!-- Жалоба -->
      <div class="form-group">
        <label for="complaint">
          <strong>Жалоба <span style="color:#c00">*</span></strong>
          <small>Что беспокоит вашего питомца?</small>
        </label>
        <textarea id="complaint" name="complaint" rows="4" maxlength="2000"
                  placeholder="Например: уже 3 дня вялая, отказывается от еды, иногда кашляет..."
                  required><?= e($form['complaint'] ?? '') ?></textarea>
        <div class="char-counter"><span id="complaintCount">0</span> / 2000</div>
      </div>

      <!-- Симптомы (чекбоксы) -->
      <div class="form-group">
        <label><strong>Симптомы</strong> <small>(отметьте всё что подходит)</small></label>
        <div class="symptoms-grid">
          <?php foreach (appointmentSymptomsList() as $key => $label): ?>
            <label class="symptom-chip">
              <input type="checkbox" name="symptoms[]" value="<?= e($key) ?>"
                     <?= in_array($key, $selectedSymptoms, true) ? 'checked' : '' ?>>
              <span><?= e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Другие симптомы -->
      <div class="form-group">
        <label for="symptoms_other"><strong>Другие симптомы</strong> <small>(если не нашли в списке)</small></label>
        <input type="text" id="symptoms_other" name="symptoms_other" maxlength="250"
               value="<?= e($form['symptoms_other'] ?? '') ?>"
               placeholder="Опишите своими словами...">
      </div>

      <!-- Возраст питомца -->
      <div class="form-group">
        <label><strong>Возраст питомца</strong></label>
        <div class="age-row">
          <input type="number" name="pet_age_value" min="0" max="50"
                 value="<?= e((string)($form['pet_age_value'] ?? '')) ?>"
                 placeholder="например: 3" style="max-width:120px;">
          <select name="pet_age_unit" style="max-width:120px;">
            <option value="years"  <?= ($form['pet_age_unit'] ?? 'years') === 'years' ? 'selected' : '' ?>>лет</option>
            <option value="months" <?= ($form['pet_age_unit'] ?? '') === 'months' ? 'selected' : '' ?>>месяцев</option>
          </select>
        </div>
      </div>

      <!-- Срочность -->
      <div class="form-group">
        <label><strong>Уровень срочности <span style="color:#c00">*</span></strong></label>
        <div class="urgency-grid">
          <?php foreach (appointmentUrgencyList() as $key => $u): ?>
            <label class="urgency-card">
              <input type="radio" name="urgency" value="<?= e($key) ?>"
                     <?= ($form['urgency'] ?? 'planned') === $key ? 'checked' : '' ?>>
              <div class="urgency-icon"><?= $u['icon'] ?></div>
              <div class="urgency-info">
                <strong><?= e($u['label']) ?></strong>
                <small><?= e($u['desc']) ?></small>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Анализы -->
      <div class="form-group">
        <label class="checkbox-row">
          <input type="checkbox" name="has_tests" value="1"
                 <?= !empty($form['has_tests']) ? 'checked' : '' ?>>
          <span><strong>У меня есть анализы или фото для врача</strong>
                <small>Отметьте если планируете показать на приёме или прикрепите ниже</small></span>
        </label>
      </div>

      <!-- Файл -->
      <div class="form-group">
        <label for="attachment">
          <strong>Прикрепить файл</strong>
          <small>JPG, PNG, WebP или PDF · до 5 МБ</small>
        </label>

        <?php if (!empty($form['attachment_file'])): ?>
          <div class="attachment-current">
            <span style="display:inline-flex;align-items:center;gap:6px;"><?= icon('paperclip', 14) ?> Текущий файл: <strong><?= e($form['attachment_file']) ?></strong></span>
            <a href="/vetclinic/uploads/appointment_attachments/<?= e($form['attachment_file']) ?>"
               target="_blank" class="btn btn-outline btn-sm">Открыть</a>
          </div>
        <?php endif; ?>

        <input type="file" id="attachment" name="attachment"
               accept="image/jpeg,image/png,image/webp,application/pdf">
        <div id="attachmentPreview" class="attachment-preview"></div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">
          <?= $form ? '💾 Сохранить изменения' : '✅ Отправить анкету' ?>
        </button>
        <a href="/vetclinic/appointments.php" class="btn btn-outline">
          <?= $form ? 'Отмена' : 'Заполнить позже' ?>
        </a>
      </div>
    </form>

  </div>
</section>

<script src="/vetclinic/assets/js/appointment-form.js" nonce="<?= e($cspNonce ?? '') ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
