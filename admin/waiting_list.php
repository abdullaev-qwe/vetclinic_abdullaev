<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/waiting_list.php';

$adminPageTitle = 'Лист ожидания';

// ── Смена статуса заявки ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $entryId   = (int)($_POST['entry_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    if ($entryId > 0 && updateWaitingStatus($db, $entryId, $newStatus)) {
        logAdminAction($db, 'Заявка #' . $entryId . ' → ' . waitingStatusLabel($newStatus));
        setFlash('success', 'Статус заявки #' . $entryId . ' обновлён.');
    }
    redirect('/vetclinic/admin/waiting_list.php');
}

// ── Фильтры ──
$filterStatus = $_GET['status'] ?? 'waiting';
$allowed = ['all','waiting','offered','accepted','expired','cancelled'];
if (!in_array($filterStatus, $allowed)) $filterStatus = 'waiting';

// ── Подсветка подходящих под отменённый слот ──
// (если пришли по ссылке вида ?from_appt_id=N — показываем подходящих)
$highlightFor = null;
if (!empty($_GET['from_appt_id'])) {
    $apptId = (int)$_GET['from_appt_id'];
    $stmt = $db->prepare(
        "SELECT doctor_id, service_id, appointment_date, appointment_time
         FROM appointments WHERE id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $apptId);
    $stmt->execute();
    $highlightFor = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($highlightFor) {
    // Подбираем подходящие заявки
    $entries = findMatchingWaitingEntries(
        $db,
        $highlightFor['doctor_id']  ? (int)$highlightFor['doctor_id']  : null,
        $highlightFor['service_id'] ? (int)$highlightFor['service_id'] : null,
        $highlightFor['appointment_date'],
        $highlightFor['appointment_time']
    );
} else {
    $where = '';
    if ($filterStatus !== 'all') {
        $where = "WHERE status = '" . $db->real_escape_string($filterStatus) . "'";
    }
    $entries = $db->query(
        "SELECT * FROM view_waiting_list $where ORDER BY created_at ASC"
    )->fetch_all(MYSQLI_ASSOC);
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($highlightFor): ?>
  <div class="alert alert-info" style="margin-bottom:20px;">
    <strong>📋 Подходящие заявки</strong> для отменённой записи на
    <?= formatDate($highlightFor['appointment_date']) ?>
    в <?= formatTime($highlightFor['appointment_time']) ?>.
    <a href="/vetclinic/admin/waiting_list.php" style="margin-left:10px;">→ Смотреть весь лист ожидания</a>
  </div>
<?php endif; ?>

<!-- Фильтры -->
<?php if (!$highlightFor): ?>
<div class="admin-filters">
  <form method="GET" action="/vetclinic/admin/waiting_list.php" class="filter-form">
    <select name="status">
      <option value="all"       <?= $filterStatus==='all'?'selected':'' ?>>Все</option>
      <option value="waiting"   <?= $filterStatus==='waiting'?'selected':'' ?>>⏳ Ожидают</option>
      <option value="offered"   <?= $filterStatus==='offered'?'selected':'' ?>>📩 Предложено</option>
      <option value="accepted"  <?= $filterStatus==='accepted'?'selected':'' ?>>✅ Записаны</option>
      <option value="expired"   <?= $filterStatus==='expired'?'selected':'' ?>>⌛ Просрочено</option>
      <option value="cancelled" <?= $filterStatus==='cancelled'?'selected':'' ?>>❌ Отменено</option>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Применить</button>
  </form>
  <span class="filter-count">Найдено: <?= count($entries) ?></span>
</div>
<?php endif; ?>

<!-- Таблица -->
<div class="admin-section" style="padding:0;overflow:hidden;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Дата заявки</th>
        <th>Пациент</th>
        <th>Питомец</th>
        <th>Хочет к врачу</th>
        <th>Период</th>
        <th>Время</th>
        <th>Статус</th>
        <th>Действия</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($entries)): ?>
      <tr>
        <td colspan="9" class="text-center text-muted" style="padding:32px;">
          <?= $highlightFor ? 'Подходящих заявок не найдено' : 'Заявок не найдено' ?>
        </td>
      </tr>
    <?php endif; ?>

    <?php foreach ($entries as $row): ?>
      <tr>
        <td><?= (int)$row['id'] ?></td>
        <td>
          <?= date('d.m.Y', strtotime($row['created_at'])) ?>
          <small><?= date('H:i', strtotime($row['created_at'])) ?></small>
        </td>
        <td>
          <strong><?= e($row['user_name']) ?></strong>
          <small>📞 <a href="tel:<?= e($row['user_phone']) ?>"><?= e($row['user_phone']) ?></a></small>
          <small>📧 <?= e($row['user_email']) ?></small>
        </td>
        <td>
          <?= e($row['pet_name']) ?>
          <small><?= e($row['pet_type']) ?></small>
        </td>
        <td>
          <?= $row['doctor_name'] ? e($row['doctor_name']) : '<em>любой</em>' ?>
          <?php if ($row['service_name']): ?>
            <small><?= e($row['service_name']) ?></small>
          <?php else: ?>
            <small><em>любая услуга</em></small>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($row['preferred_date_from'] || $row['preferred_date_to']): ?>
            <?php if ($row['preferred_date_from']): ?>
              с <?= date('d.m', strtotime($row['preferred_date_from'])) ?>
            <?php endif; ?>
            <?php if ($row['preferred_date_to']): ?>
              по <?= date('d.m', strtotime($row['preferred_date_to'])) ?>
            <?php endif; ?>
          <?php else: ?>
            <em>любой</em>
          <?php endif; ?>
        </td>
        <td>
          <?php
          $timeLabels = ['any'=>'Любое','morning'=>'Утро','afternoon'=>'День','evening'=>'Вечер'];
          echo e($timeLabels[$row['preferred_time']] ?? 'Любое');
          ?>
        </td>
        <td><?= waitingStatusBadge($row['status']) ?></td>
        <td>
          <form method="POST" action="/vetclinic/admin/waiting_list.php" class="inline-form">
            <input type="hidden" name="action"   value="update_status">
            <input type="hidden" name="entry_id" value="<?= (int)$row['id'] ?>">
            <select name="status" class="select-sm">
              <?php foreach (['waiting','offered','accepted','expired','cancelled'] as $st): ?>
                <option value="<?= $st ?>" <?= $row['status']===$st?'selected':'' ?>>
                  <?= waitingStatusLabel($st) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Сохранить</button>
          </form>
          <?php if ($row['notes']): ?>
            <small class="text-muted" style="display:block;margin-top:4px;">
              💬 <?= e(mb_strimwidth($row['notes'], 0, 80, '…')) ?>
            </small>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
