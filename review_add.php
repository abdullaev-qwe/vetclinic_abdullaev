<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

requireUserAuth();

$userId  = (int)$_SESSION['user_id'];
$apptId  = (int)($_GET['appt_id'] ?? 0);
$errors  = [];
$success = false;

if ($apptId <= 0) redirect('/vetclinic/appointments.php');

// Проверяем что запись завершена и принадлежит этому пользователю
$stmt = $db->prepare(
    "SELECT a.*, d.name AS doctor_name, s.name AS service_name
     FROM appointments a
     JOIN doctors d ON a.doctor_id = d.id
     JOIN services s ON a.service_id = s.id
     WHERE a.id = ? AND a.user_id = ? AND a.status = 'completed'
     LIMIT 1"
);
$stmt->bind_param('ii', $apptId, $userId);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appt) {
    setFlash('danger', 'Отзыв можно оставить только после завершённого приёма.');
    redirect('/vetclinic/appointments.php');
}

// Проверяем не оставлял ли уже отзыв на этот приём
$stmt = $db->prepare(
    "SELECT id FROM reviews
     WHERE user_id = ? AND doctor_id = ?
       AND DATE(created_at) = ?
     LIMIT 1"
);
$apptDate = $appt['appointment_date'];
$stmt->bind_param('iis', $userId, $appt['doctor_id'], $apptDate);
$stmt->execute();
$stmt->store_result();
$alreadyReviewed = $stmt->num_rows > 0;
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyReviewed) {
    verifyCsrf();

    $rating = (int)($_POST['rating'] ?? 0);
    $text   = trim($_POST['review_text'] ?? '');

    if ($rating < 1 || $rating > 5) $errors[] = 'Выберите оценку от 1 до 5.';
    if (mb_strlen($text) < 10)      $errors[] = 'Напишите отзыв (минимум 10 символов).';

    if (empty($errors)) {
        $doctorId = $appt['doctor_id'];
        $stmt = $db->prepare(
            "INSERT INTO reviews (user_id, doctor_id, rating, review_text, is_visible)
             VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->bind_param('iiis', $userId, $doctorId, $rating, $text);
        $stmt->execute();
        $stmt->close();

        setFlash('success', 'Спасибо за отзыв! Он опубликован на сайте.');
        redirect('/vetclinic/appointments.php');
    }
}

$pageTitle = 'Оставить отзыв';
require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section" style="align-items:flex-start;padding-top:60px;">
  <div class="container">
    <div class="auth-box" style="max-width:560px;">
      <div class="auth-header">
        <h1>Оставить отзыв</h1>
        <p>Поделитесь впечатлением о визите</p>
      </div>

      <!-- Информация о приёме -->
      <div style="background:var(--emerald-ghost);border-radius:var(--radius);
                  padding:16px 20px;margin-bottom:24px;border:1px solid var(--border);">
        <p style="font-size:.82rem;color:var(--ink-muted);margin-bottom:4px;
                  text-transform:uppercase;letter-spacing:.06em;font-weight:700;">
          Ваш приём
        </p>
        <p style="font-weight:700;color:var(--ink);"><?= e($appt['doctor_name']) ?></p>
        <p style="color:var(--ink-soft);font-size:.9rem;">
          <?= e($appt['service_name']) ?> · <?= formatDate($appt['appointment_date']) ?>
        </p>
      </div>

      <?php if ($alreadyReviewed): ?>
        <div class="alert alert-info">
          Вы уже оставили отзыв об этом приёме.
        </div>
        <a href="/vetclinic/appointments.php" class="btn btn-outline btn-full">
          Вернуться к записям
        </a>

      <?php else: ?>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <form method="POST"
              action="/vetclinic/review_add.php?appt_id=<?= $apptId ?>"
              novalidate>
          <?= csrfField() ?>

          <!-- Звёздочки -->
          <div class="form-group">
            <label>Оценка *</label>
            <div class="star-rating" id="starRating">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <button type="button" class="star-btn" data-value="<?= $i ?>">★</button>
              <?php endfor; ?>
            </div>
            <input type="hidden" name="rating" id="ratingInput" value="0">
            <p id="ratingLabel" style="font-size:.82rem;color:var(--ink-muted);margin-top:6px;">
              Нажмите на звезду для оценки
            </p>
          </div>

          <div class="form-group">
            <label for="review_text">Ваш отзыв *</label>
            <textarea id="review_text" name="review_text" rows="5"
                      placeholder="Расскажите о визите: как прошёл приём, насколько помог врач..."
                      required maxlength="2000"></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-full">
            Опубликовать отзыв
          </button>
        </form>

        <div class="auth-footer">
          <p><a href="/vetclinic/appointments.php">← Вернуться к записям</a></p>
        </div>

      <?php endif; ?>
    </div>
  </div>
</section>

<style nonce="<?= e($cspNonce ?? '') ?>">
.star-rating { display:flex; gap:6px; margin-bottom:4px; }
.star-btn {
  font-size:2rem; background:none; border:none; cursor:pointer;
  color:var(--border); transition:color .15s; line-height:1; padding:0;
}
.star-btn.active { color:var(--gold); }
.star-btn:hover  { color:var(--gold); }
</style>

<script nonce="<?= e($cspNonce ?? '') ?>">
(function() {
    var labels = ['','Плохо','Неплохо','Хорошо','Очень хорошо','Отлично!'];
    var stars   = document.querySelectorAll('.star-btn');
    var input   = document.getElementById('ratingInput');
    var label   = document.getElementById('ratingLabel');

    stars.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var val = parseInt(this.getAttribute('data-value'));
            input.value = val;
            label.textContent = labels[val] || '';
            stars.forEach(function(s) {
                s.classList.toggle('active', parseInt(s.getAttribute('data-value')) <= val);
            });
        });
        btn.addEventListener('mouseover', function() {
            var val = parseInt(this.getAttribute('data-value'));
            stars.forEach(function(s) {
                s.style.color = parseInt(s.getAttribute('data-value')) <= val ? 'var(--gold)' : '';
            });
        });
        btn.addEventListener('mouseout', function() {
            var cur = parseInt(input.value);
            stars.forEach(function(s) {
                var v = parseInt(s.getAttribute('data-value'));
                s.style.color = '';
                s.classList.toggle('active', cur > 0 && v <= cur);
            });
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
