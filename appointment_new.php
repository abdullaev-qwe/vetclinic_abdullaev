<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

if (!isUserLoggedIn()) redirect('/vetclinic/login.php?redirect=/vetclinic/appointment_new.php');

$userId   = (int)$_SESSION['user_id'];
$errors   = [];
$doctors  = getActiveDoctors($db);
$services = getActiveServices($db);
$minDate  = date('Y-m-d', strtotime('+1 day'));
$maxDate  = date('Y-m-d', strtotime('+3 months'));

$form = [
    'doctor_id'        => (int)($_POST['doctor_id']        ?? 0),
    'service_id'       => (int)($_POST['service_id']       ?? 0),
    'pet_name'         => trim($_POST['pet_name']           ?? ''),
    'pet_type'         => trim($_POST['pet_type']           ?? ''),
    'appointment_date' => trim($_POST['appointment_date']   ?? ''),
    'appointment_time' => trim($_POST['appointment_time']   ?? ''),
    'comment'          => trim($_POST['comment']            ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if ($form['doctor_id']  <= 0)   $errors[] = 'Выберите врача.';
    if ($form['service_id'] <= 0)   $errors[] = 'Выберите услугу.';
    if (empty($form['pet_name']))    $errors[] = 'Укажите имя питомца.';
    if (empty($form['pet_type']))    $errors[] = 'Укажите вид питомца.';
    if (empty($form['appointment_date']))       $errors[] = 'Выберите дату.';
    elseif ($form['appointment_date'] < $minDate) $errors[] = 'Дата не может быть раньше завтрашнего дня.';
    if (empty($form['appointment_time'])) $errors[] = 'Выберите время.';

    if (empty($errors)) {
        // Проверка занятости слота
        $stmt = $db->prepare(
            "SELECT id FROM appointments
             WHERE doctor_id=? AND appointment_date=? AND appointment_time=?
               AND status IN ('new','confirmed') LIMIT 1"
        );
        $stmt->bind_param('iss', $form['doctor_id'], $form['appointment_date'], $form['appointment_time']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $errors[] = 'Это время уже занято. Выберите другое.';
        $stmt->close();
    }

    if (empty($errors)) {
        $stmt = $db->prepare(
            "INSERT INTO appointments
             (user_id, doctor_id, service_id, pet_name, pet_type,
              appointment_date, appointment_time, comment)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('iiisssss',
            $userId, $form['doctor_id'], $form['service_id'],
            $form['pet_name'], $form['pet_type'],
            $form['appointment_date'], $form['appointment_time'], $form['comment']
        );
        if ($stmt->execute()) {
            $apptId = (int)$db->insert_id;
            $stmt->close();

            // Отправляем email пользователю
            $userInfo = $db->prepare('SELECT email, name FROM users WHERE id = ?');
            $userInfo->bind_param('i', $userId);
            $userInfo->execute();
            $user = $userInfo->get_result()->fetch_assoc();
            $userInfo->close();

            $doctorInfo  = array_filter($doctors,  fn($d) => (int)$d['id'] === $form['doctor_id']);
            $serviceInfo = array_filter($services, fn($s) => (int)$s['id'] === $form['service_id']);
            $doctorName  = $doctorInfo  ? reset($doctorInfo)['name']  : '';
            $serviceName = $serviceInfo ? reset($serviceInfo)['name'] : '';

            @sendAppointmentCreatedEmail($user['email'], [
                'user_name' => $user['name'],
                'doctor'    => $doctorName,
                'service'   => $serviceName,
                'date'      => $form['appointment_date'],
                'time'      => $form['appointment_time'],
                'pet_name'  => $form['pet_name'],
                'pet_type'  => $form['pet_type'],
            ]);

            setFlash('success', 'Запись создана! Заполните анкету для врача.');
            redirect('/vetclinic/appointment_form.php?id=' . $apptId);
        } else {
            $errors[] = 'Ошибка при сохранении. Попробуйте ещё раз.';
            $stmt->close();
        }
    }
}

$pageTitle = 'Запись на приём';
require_once __DIR__ . '/includes/header.php';
?>

<section class="appointment-form-section">
  <div class="container">
    <div class="form-box form-box--wide">
      <div class="auth-header">
        <h1>Запись на приём</h1>
        <p>Заполните форму, и мы подтвердим запись в течение часа</p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <!-- ═══ Баннер: лист ожидания ═══ -->
      <div class="waiting-list-banner"
           style="display:flex !important;
                  background:linear-gradient(135deg,#fff8e1 0%,#fdf6e3 100%) !important;
                  border:1px solid #e2c97e !important;
                  color:#6b4d00 !important;
                  padding:16px 20px !important;
                  border-radius:10px !important;
                  align-items:center !important;
                  gap:14px !important;
                  margin-bottom:24px !important;
                  flex-wrap:wrap !important;
                  visibility:visible !important;
                  opacity:1 !important;">
        <div style="font-size:1.6rem;flex-shrink:0;">⏳</div>
        <div style="flex:1;min-width:240px;color:#6b4d00;">
          <strong style="display:block;margin-bottom:2px;color:#6b4d00;">Не подходит свободное время?</strong>
          <small style="color:#8a6500;">Запишитесь в лист ожидания — мы сообщим, когда появится место</small>
        </div>
        <a href="/vetclinic/waiting_list_join.php"
           style="display:inline-flex;align-items:center;gap:6px;
                  padding:8px 18px;background:#0d4f3c;color:#fff;
                  border-radius:8px;text-decoration:none;
                  font-weight:600;font-size:.85rem;
                  white-space:nowrap;">
          В лист ожидания →
        </a>
      </div>
      <!-- ═══ /Баннер ═══ -->

      <form method="POST" action="/vetclinic/appointment_new.php" novalidate>
        <?= csrfField() ?>

        <div class="form-row">
          <div class="form-group">
            <label for="doctor_id">Врач *</label>
            <select id="doctor_id" name="doctor_id" required>
              <option value="">— Выберите врача —</option>
              <?php foreach ($doctors as $doc): ?>
                <option value="<?= (int)$doc['id'] ?>"
                  <?= $form['doctor_id']===(int)$doc['id']?'selected':'' ?>>
                  <?= e($doc['name']) ?> — <?= e($doc['specialty']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="service_id">Услуга *</label>
            <select id="service_id" name="service_id" required>
              <option value="">— Выберите услугу —</option>
              <?php foreach ($services as $svc): ?>
                <option value="<?= (int)$svc['id'] ?>"
                  <?= $form['service_id']===(int)$svc['id']?'selected':'' ?>>
                  <?= e($svc['name']) ?> — <?= number_format($svc['price'],0,'.',' ') ?> ₽
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="appointment_date">Дата *</label>
            <input type="text" id="appointment_date" name="appointment_date"
                   class="flatpickr-input"
                   value="<?= e($form['appointment_date']) ?>"
                   placeholder="Выберите дату приёма"
                   data-min="<?= $minDate ?>" data-max="<?= $maxDate ?>"
                   readonly required>
          </div>
          <div class="form-group">
            <label for="appointment_time">Время *</label>
            <select id="appointment_time" name="appointment_time" required>
              <option value="">— Время —</option>
              <?php
              $times=['09:00','09:30','10:00','10:30','11:00','11:30',
                      '12:00','12:30','13:00','14:00','14:30','15:00',
                      '15:30','16:00','16:30','17:00','17:30','18:00'];
              foreach ($times as $t):
              ?>
                <option value="<?= $t ?>" <?= $form['appointment_time']===$t?'selected':'' ?>>
                  <?= $t ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="pet_name">Имя питомца *</label>
            <input type="text" id="pet_name" name="pet_name"
                   value="<?= e($form['pet_name']) ?>" placeholder="Барсик" required maxlength="100">
          </div>
          <div class="form-group">
            <label for="pet_type">Вид питомца *</label>
            <select id="pet_type" name="pet_type" required>
              <option value="">— Вид —</option>
              <?php foreach (['Кошка','Кот','Собака','Грызун','Кролик','Птица','Рептилия','Другое'] as $pt): ?>
                <option value="<?= $pt ?>" <?= $form['pet_type']===$pt?'selected':'' ?>><?= $pt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="comment">Комментарий</label>
          <textarea id="comment" name="comment" rows="3"
                    placeholder="Опишите жалобы или особенности..." maxlength="1000"><?= e($form['comment']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-full">Записаться на приём</button>
      </form>
    </div>
  </div>
</section>


<!-- Flatpickr — красивый календарь -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/themes/airbnb.css">
<script nonce="<?= e($cspNonce ?? '') ?>" src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script nonce="<?= e($cspNonce ?? '') ?>" src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ru.js"></script>

<style nonce="<?= e($cspNonce ?? '') ?>">
.flatpickr-input { cursor: pointer; background-color: var(--white) !important; }
.flatpickr-calendar { font-family: var(--font-body, system-ui); border-radius: 12px; box-shadow: 0 8px 28px rgba(0,0,0,.12); }
.flatpickr-calendar.airbnb .flatpickr-day.selected,
.flatpickr-calendar.airbnb .flatpickr-day.startRange,
.flatpickr-calendar.airbnb .flatpickr-day.endRange {
    background: var(--emerald, #0d4f3c);
    border-color: var(--emerald, #0d4f3c);
}
.flatpickr-calendar.airbnb .flatpickr-day.today {
    border-color: var(--gold, #c9a84c);
}
.flatpickr-day.flatpickr-disabled {
    color: rgba(72, 72, 72, 0.3);
    text-decoration: line-through;
}
[data-theme="dark"] .flatpickr-calendar {
    background: var(--white, #1e2826);
    color: var(--ink, #e8f0ec);
    border: 1px solid var(--border, #2d3d37);
}
[data-theme="dark"] .flatpickr-day { color: var(--ink, #e8f0ec); }
[data-theme="dark"] .flatpickr-day:hover { background: var(--emerald-ghost, #152620); }
[data-theme="dark"] .flatpickr-current-month { color: var(--ink, #e8f0ec); }
[data-theme="dark"] .flatpickr-monthDropdown-months,
[data-theme="dark"] .flatpickr-monthDropdown-month { background: var(--white, #1e2826); color: var(--ink); }
[data-theme="dark"] .flatpickr-weekday { color: var(--ink-soft, #9db4ac); }
</style>

<script nonce="<?= e($cspNonce ?? '') ?>">
document.addEventListener("DOMContentLoaded", function() {
    var input = document.getElementById("appointment_date");
    if (!input || typeof flatpickr === "undefined") return;

    flatpickr(input, {
        locale: "ru",
        dateFormat: "Y-m-d",
        altInput: true,
        altFormat: "j F Y (l)",
        minDate: input.dataset.min,
        maxDate: input.dataset.max,
        disable: [
            function(date) {
                // Запрещаем воскресенья (0) — клиника не работает
                return date.getDay() === 0;
            }
        ],
        onChange: function(selectedDates, dateStr) {
            input.value = dateStr;
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
