<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/waiting_list.php';

requireUserAuth();

$userId = (int)$_SESSION['user_id'];

$pageError = '';
$errors    = [];

$prefilledDoctor  = (int)($_GET['doctor_id']  ?? 0);
$prefilledService = (int)($_GET['service_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';
    $csrfValid = false;
    if (function_exists('checkCsrfToken')) {
        $csrfValid = checkCsrfToken($csrfToken);
    } elseif (function_exists('verifyCsrf')) {
        try { verifyCsrf(); $csrfValid = true; } catch (\Throwable $e) {}
    } else {
        $csrfValid = !empty($_SESSION['csrf_token'])
                  && hash_equals($_SESSION['csrf_token'], $csrfToken);
    }

    if (!$csrfValid) {
        $pageError = 'Ошибка безопасности.';
    } else {
        $result = createWaitingListEntry($db, $userId, $_POST);
        if ($result['ok']) {
            setFlash('success', '✅ Вы добавлены в лист ожидания. Мы сообщим, когда появится место.');
            redirect('/vetclinic/waiting_list_my.php');
        } else {
            $errors = $result['errors'];
        }
    }
}

// Данные для формы
$doctors = $db->query(
    "SELECT id, name, specialty FROM doctors WHERE is_active = 1 ORDER BY name"
)->fetch_all(MYSQLI_ASSOC);

$services = $db->query(
    "SELECT id, name FROM services WHERE is_active = 1 ORDER BY name"
)->fetch_all(MYSQLI_ASSOC);

// CSRF токен
$csrfToken = function_exists('generateCsrfToken')
    ? generateCsrfToken()
    : ($_SESSION['csrf_token'] ?? '');

$pageTitle = 'Лист ожидания';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
  <div class="container">
    <h1>⏳ Записаться в лист ожидания</h1>
    <p>Если в желаемое время нет свободных окон — мы сообщим как только кто-то отменит запись</p>
  </div>
</div>

<section class="page-section">
  <div class="container appt-form-container">

    <?php showFlash(); ?>

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

    <div class="alert alert-info" style="margin-bottom:24px;">
      <strong>Как это работает:</strong>
      <ol style="margin:8px 0 0;padding-left:20px;">
        <li>Заполните анкету ниже — кого и когда вы хотите попасть на приём</li>
        <li>Когда другой пациент отменит запись на подходящие время/врача — администратор увидит вас</li>
        <li>Вам позвонят и предложат свободное место</li>
      </ol>
    </div>

    <form method="POST" action="/vetclinic/waiting_list_join.php" class="appt-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

      <div class="form-group">
        <label for="pet_name"><strong>Имя питомца <span style="color:#c00">*</span></strong></label>
        <input type="text" id="pet_name" name="pet_name" maxlength="80" required
               value="<?= e($_POST['pet_name'] ?? '') ?>"
               placeholder="например: Барсик">
      </div>

      <div class="form-group">
        <label for="pet_type"><strong>Вид животного <span style="color:#c00">*</span></strong></label>
        <select id="pet_type" name="pet_type" required>
          <option value="">— выберите —</option>
          <?php
          $types = ['Собака','Кошка','Птица','Грызун','Рептилия','Экзотическое','Другое'];
          $selectedType = $_POST['pet_type'] ?? '';
          foreach ($types as $t): ?>
            <option value="<?= e($t) ?>" <?= $selectedType === $t ? 'selected' : '' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="doctor_id"><strong>Врач</strong> <small>необязательно</small></label>
        <select id="doctor_id" name="doctor_id">
          <option value="">— любой врач —</option>
          <?php foreach ($doctors as $d):
            $sel = ((int)($_POST['doctor_id'] ?? $prefilledDoctor) === (int)$d['id']) ? 'selected' : '';
          ?>
            <option value="<?= (int)$d['id'] ?>" <?= $sel ?>>
              <?= e($d['name']) ?> · <?= e($d['specialty']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="service_id"><strong>Услуга</strong> <small>необязательно</small></label>
        <select id="service_id" name="service_id">
          <option value="">— любая услуга —</option>
          <?php foreach ($services as $s):
            $sel = ((int)($_POST['service_id'] ?? $prefilledService) === (int)$s['id']) ? 'selected' : '';
          ?>
            <option value="<?= (int)$s['id'] ?>" <?= $sel ?>>
              <?= e($s['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label><strong>Желаемый период</strong> <small>необязательно — если не важно, оставьте пустым</small></label>
        <div class="age-row">
          <div style="flex:1">
            <small style="display:block;color:var(--ink-soft);margin-bottom:4px;">С даты</small>
            <input type="date" name="preferred_date_from"
                   min="<?= date('Y-m-d') ?>"
                   value="<?= e($_POST['preferred_date_from'] ?? '') ?>">
          </div>
          <div style="flex:1">
            <small style="display:block;color:var(--ink-soft);margin-bottom:4px;">По дату</small>
            <input type="date" name="preferred_date_to"
                   min="<?= date('Y-m-d') ?>"
                   value="<?= e($_POST['preferred_date_to'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label><strong>Удобное время</strong></label>
        <div class="urgency-grid">
          <?php foreach (waitingTimePreferences() as $key => $label):
            $sel = ($_POST['preferred_time'] ?? 'any') === $key ? 'checked' : '';
          ?>
            <label class="urgency-card">
              <input type="radio" name="preferred_time" value="<?= e($key) ?>" <?= $sel ?>>
              <div class="urgency-info">
                <strong><?= e($label) ?></strong>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-group">
        <label for="notes"><strong>Комментарий</strong> <small>необязательно</small></label>
        <textarea id="notes" name="notes" rows="3" maxlength="500"
                  placeholder="Что-то важное, что мы должны знать?"><?= e($_POST['notes'] ?? '') ?></textarea>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">⏳ Добавить в лист ожидания</button>
        <a href="/vetclinic/appointment_new.php" class="btn btn-outline">Отмена</a>
      </div>
    </form>

  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
