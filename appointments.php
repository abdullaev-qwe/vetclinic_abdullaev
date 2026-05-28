<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

requireUserAuth();

$userId = (int)$_SESSION['user_id'];
$filterStatus = $_GET['status'] ?? 'all';
$allowed = ['all','new','confirmed','completed','cancelled'];
if (!in_array($filterStatus, $allowed)) $filterStatus = 'all';

if ($filterStatus === 'all') {
    $stmt = $db->prepare(
        "SELECT * FROM view_user_appointments
         WHERE user_id = ? ORDER BY appointment_date DESC, appointment_time DESC"
    );
    $stmt->bind_param('i', $userId);
} else {
    $stmt = $db->prepare(
        "SELECT * FROM view_user_appointments
         WHERE user_id = ? AND status = ?
         ORDER BY appointment_date DESC, appointment_time DESC"
    );
    $stmt->bind_param('is', $userId, $filterStatus);
}
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Проверяем какие завершённые приёмы уже имеют отзыв
$reviewed = [];
if (!empty($appointments)) {
    $res = $db->query(
        "SELECT doctor_id, DATE(created_at) AS rev_date
         FROM reviews WHERE user_id = $userId"
    );
    while ($row = $res->fetch_assoc()) {
        $reviewed[$row['doctor_id'] . '_' . $row['rev_date']] = true;
    }
}

$pageTitle = 'Мои записи';
require_once __DIR__ . '/includes/header.php';
?>

<section class="appointments-section">
  <div class="container">
    <?php showFlash(); ?>

    <div class="page-header">
      <h1>Мои записи на приём</h1>
      <a href="/vetclinic/appointment_new.php" class="btn btn-gold">+ Новая запись</a>
    </div>

    <div class="filter-tabs">
      <?php
      $tabs = ['all'=>'Все','new'=>'Новые','confirmed'=>'Подтверждённые',
               'completed'=>'Завершённые','cancelled'=>'Отменённые'];
      foreach ($tabs as $val => $label):
      ?>
        <a href="?status=<?= $val ?>"
           class="filter-tab <?= $filterStatus===$val?'active':'' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Skeleton-плейсхолдер для записей -->
    <div data-skeleton-target class="skeleton-loading">
      <div class="skeleton-container">
        <?php for ($i = 0; $i < 3; $i++): ?>
          <div class="skeleton-appointment-card">
            <div class="skeleton skeleton-date"></div>
            <div class="skeleton-info">
              <div class="skeleton skeleton-text skeleton-title"></div>
              <div class="skeleton skeleton-text skeleton-line-80"></div>
              <div class="skeleton skeleton-text skeleton-line-60"></div>
            </div>
            <div class="skeleton skeleton-badge"></div>
          </div>
        <?php endfor; ?>
      </div>
      <div class="real-content">

        <?php if (empty($appointments)): ?>
          <div class="empty-state">
            <p>Записей не найдено.</p>
            <a href="/vetclinic/appointment_new.php" class="btn btn-primary">Записаться на приём</a>
          </div>
        <?php else: ?>
          <div class="appointments-table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Дата и время</th><th>Услуга</th><th>Врач</th>
                  <th>Питомец</th><th>Статус</th><th>Действия</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($appointments as $appt): ?>
                <tr>
                  <td>
                    <strong><?= formatDate($appt['appointment_date']) ?></strong><br>
                    <span class="text-muted"><?= formatTime($appt['appointment_time']) ?></span>
                  </td>
                  <td>
                    <?= e($appt['service_name']) ?><br>
                    <span class="text-muted price">
                      <?= number_format((float)$appt['service_price'],0,'.',' ') ?> ₽
                    </span>
                  </td>
                  <td>
                    <?= e($appt['doctor_name']) ?><br>
                    <span class="text-muted"><?= e($appt['doctor_specialty']) ?></span>
                  </td>
                  <td>
                    <?= e($appt['pet_name']) ?><br>
                    <span class="text-muted"><?= e($appt['pet_type']) ?></span>
                  </td>
                  <td>
                    <span class="badge <?= statusBadgeClass($appt['status']) ?>">
                      <?= statusLabel($appt['status']) ?>
                    </span>
                    <?php if (!empty($appt['form_urgency']) && $appt['form_urgency'] !== 'planned'): ?>
                      <br>
                      <?php
                      $urgencyColors = [
                          'week'      => ['bg' => '#fff8e1', 'fg' => '#8a6500', 'icon' => '🟡'],
                          'urgent'    => ['bg' => '#fff3e0', 'fg' => '#bf360c', 'icon' => '🟠'],
                          'emergency' => ['bg' => '#ffebee', 'fg' => '#b71c1c', 'icon' => '🔴'],
                      ];
                      $urgencyLabels = [
                          'week'      => 'В течение недели',
                          'urgent'    => 'Срочно',
                          'emergency' => 'Экстренно',
                      ];
                      $u = $urgencyColors[$appt['form_urgency']] ?? null;
                      $ul = $urgencyLabels[$appt['form_urgency']] ?? '';
                      if ($u):
                      ?>
                        <span style="display:inline-flex;align-items:center;gap:4px;
                                     background:<?= $u['bg'] ?>;color:<?= $u['fg'] ?>;
                                     padding:2px 8px;border-radius:10px;font-size:.72rem;
                                     font-weight:600;margin-top:4px;">
                          <?= $u['icon'] ?> <?= e($ul) ?>
                        </span>
                      <?php endif; ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (in_array($appt['status'], ['new','confirmed'])): ?>
                      <!-- Кнопка анкеты -->
                      <?php if (empty($appt['form_id'])): ?>
                        <a href="/vetclinic/appointment_form.php?id=<?= (int)$appt['id'] ?>"
                           class="btn btn-sm btn-outline" style="margin-bottom:4px;">
                          📋 Заполнить анкету
                        </a><br>
                      <?php else: ?>
                        <a href="/vetclinic/appointment_form.php?id=<?= (int)$appt['id'] ?>"
                           class="btn btn-sm btn-ghost" style="margin-bottom:4px;color:#27ae60;">
                          ✓ Анкета заполнена
                        </a><br>
                      <?php endif; ?>

                      <a href="/vetclinic/appointment_cancel.php?id=<?= (int)$appt['id'] ?>"
                         class="btn btn-sm btn-danger"
                         onclick="return confirm('Отменить запись?')">
                        Отменить
                      </a>
                    <?php elseif ($appt['status'] === 'completed'): ?>
                      <?php
                      $key = $appt['doctor_id'] . '_' . $appt['appointment_date'];
                      if (!isset($reviewed[$key])):
                      ?>
                        <a href="/vetclinic/review_add.php?appt_id=<?= (int)$appt['id'] ?>"
                           class="btn btn-sm btn-outline">
                          Оставить отзыв
                        </a>
                      <?php else: ?>
                        <span class="text-muted" style="font-size:.82rem;">✓ Отзыв оставлен</span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <div class="back-link">
          <a href="/vetclinic/profile.php">← Вернуться в кабинет</a>
        </div>

      </div><!-- /.real-content -->
    </div><!-- /[data-skeleton-target] -->

  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
