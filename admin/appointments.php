<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/../includes/render_appointment_form.php';

$adminPageTitle = 'Записи на приём';

// ── Смена статуса ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $id      = (int)($_POST['appt_id'] ?? 0);
    $status  = $_POST['status'] ?? '';
    $allowed = ['new','confirmed','cancelled','completed'];
    if ($id > 0 && in_array($status, $allowed)) {
        $stmt = $db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $stmt->close();

        // Логирование
        logAdminAction($db, 'Изменён статус записи #' . $id . ' → ' . statusLabel($status));
        setFlash('success', 'Статус записи #' . $id . ' → «' . statusLabel($status) . '».');
    }
    redirect('/vetclinic/admin/appointments.php');
}

// ── Фильтры ──
$filterStatus = $_GET['status']    ?? 'all';
$filterDoctor = (int)($_GET['doctor_id'] ?? 0);
$filterUrgency = $_GET['urgency']  ?? 'all';
$allowed = ['all','new','confirmed','cancelled','completed'];
$allowedUrgency = ['all','planned','week','urgent','emergency'];
if (!in_array($filterStatus, $allowed)) $filterStatus = 'all';
if (!in_array($filterUrgency, $allowedUrgency)) $filterUrgency = 'all';

// ── Пагинация ──
$perPage     = 20;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

// ── Формируем WHERE ──
$where  = [];
$params = [];
$types  = '';

if ($filterStatus !== 'all') { $where[] = 'status = ?'; $params[] = $filterStatus; $types .= 's'; }
if ($filterDoctor > 0)       { $where[] = 'doctor_id = ?'; $params[] = $filterDoctor; $types .= 'i'; }
if ($filterUrgency !== 'all'){ $where[] = 'form_urgency = ?'; $params[] = $filterUrgency; $types .= 's'; }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Считаем общее количество ──
$countSQL = "SELECT COUNT(*) AS cnt FROM view_all_appointments $whereSQL";
$stmtC = $db->prepare($countSQL);
if ($params) $stmtC->bind_param($types, ...$params);
$stmtC->execute();
$totalRows  = (int)$stmtC->get_result()->fetch_assoc()['cnt'];
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$stmtC->close();
if ($currentPage > $totalPages) $currentPage = $totalPages;

// ── Экспорт CSV ──
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmtE = $db->prepare("SELECT * FROM view_all_appointments $whereSQL ORDER BY appointment_date DESC");
    if ($params) $stmtE->bind_param($types, ...$params);
    $stmtE->execute();
    $rows = $stmtE->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtE->close();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="appointments_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['#','Дата','Время','Пользователь','Телефон','Врач','Услуга','Цена','Питомец','Вид','Статус'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['appointment_date'], substr($r['appointment_time'],0,5),
            $r['user_name'], $r['user_phone'], $r['doctor_name'],
            $r['service_name'], $r['service_price'],
            $r['pet_name'], $r['pet_type'], statusLabel($r['status'])
        ], ';');
    }
    fclose($out);
    exit;
}

// ── Получаем записи с пагинацией ──
$limitParams   = $params;
$limitTypes    = $types;
$limitParams[] = $perPage;
$limitParams[] = $offset;
$limitTypes   .= 'ii';

$stmt = $db->prepare("SELECT * FROM view_all_appointments $whereSQL ORDER BY appointment_date DESC, appointment_time DESC LIMIT ? OFFSET ?");
$stmt->bind_param($limitTypes, ...$limitParams);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$doctors = getAllDoctors($db);

// ── Строим URL для пагинации ──
function pageUrl(int $page): string {
    $params = $_GET;
    $params['page'] = $page;
    unset($params['export']);
    return '?' . http_build_query($params);
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<!-- Фильтры + Экспорт -->
<div class="admin-filters">
  <form method="GET" action="/vetclinic/admin/appointments.php" class="filter-form">
    <select name="status">
      <option value="all" <?= $filterStatus==='all'?'selected':'' ?>>Все статусы</option>
      <?php foreach (['new'=>'Новые','confirmed'=>'Подтверждённые','cancelled'=>'Отменённые','completed'=>'Завершённые'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $filterStatus===$v?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <select name="doctor_id">
      <option value="0">Все врачи</option>
      <?php foreach ($doctors as $doc): ?>
        <option value="<?= (int)$doc['id'] ?>" <?= $filterDoctor===(int)$doc['id']?'selected':'' ?>>
          <?= e($doc['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <select name="urgency">
      <option value="all"       <?= $filterUrgency==='all'?'selected':'' ?>>Любая срочность</option>
      <option value="planned"   <?= $filterUrgency==='planned'?'selected':'' ?>>🟢 Плановый</option>
      <option value="week"      <?= $filterUrgency==='week'?'selected':'' ?>>🟡 В течение недели</option>
      <option value="urgent"    <?= $filterUrgency==='urgent'?'selected':'' ?>>🟠 Срочно</option>
      <option value="emergency" <?= $filterUrgency==='emergency'?'selected':'' ?>>🔴 Экстренно</option>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Применить</button>
    <a href="/vetclinic/admin/appointments.php" class="btn btn-ghost btn-sm">Сбросить</a>
  </form>
  <div style="display:flex;align-items:center;gap:12px;">
    <span class="filter-count">Найдено: <?= $totalRows ?></span>
    <a href="/vetclinic/admin/export_xlsx.php"
       class="btn btn-success btn-sm"
       style="background:#27ae60;color:#fff;border-color:#27ae60;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
      Скачать Excel
    </a>
    <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>"
       class="btn btn-outline btn-sm">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
      Экспорт CSV
    </a>
  </div>
</div>

<!-- Таблица -->
<div class="admin-section" style="padding:0;overflow:hidden;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>#</th><th>Дата / Время</th><th>Пользователь</th>
        <th>Врач</th><th>Услуга</th><th>Питомец</th>
        <th>Статус</th><th>Действия</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($appointments)): ?>
      <tr><td colspan="8" class="text-center text-muted" style="padding:32px;">Записей не найдено</td></tr>
    <?php endif; ?>
    <?php foreach ($appointments as $row): ?>
      <tr>
        <td><?= (int)$row['id'] ?></td>
        <td><?= formatDate($row['appointment_date']) ?><small><?= formatTime($row['appointment_time']) ?></small></td>
        <td><?= e($row['user_name']) ?><small><?= e($row['user_phone']) ?></small></td>
        <td><?= e($row['doctor_name']) ?><small><?= e($row['doctor_specialty']) ?></small></td>
        <td><?= e($row['service_name']) ?><small><?= number_format((float)$row['service_price'],0,'.',' ') ?> ₽</small></td>
        <td><?= e($row['pet_name']) ?><small><?= e($row['pet_type']) ?></small></td>
        <td>
          <span class="badge <?= statusBadgeClass($row['status']) ?>"><?= statusLabel($row['status']) ?></span>
          <?php if (!empty($row['form_urgency']) && in_array($row['form_urgency'], ['urgent','emergency'])): ?>
            <?php
            $urg = $row['form_urgency'];
            $colors = [
                'urgent'    => ['bg' => '#fff3e0', 'fg' => '#bf360c', 'icon' => '🟠', 'lbl' => 'Срочно'],
                'emergency' => ['bg' => '#ffebee', 'fg' => '#b71c1c', 'icon' => '🔴', 'lbl' => 'Экстренно'],
            ];
            $c = $colors[$urg];
            ?>
            <br>
            <span style="display:inline-flex;align-items:center;gap:4px;
                         background:<?= $c['bg'] ?>;color:<?= $c['fg'] ?>;
                         padding:2px 8px;border-radius:10px;font-size:.72rem;
                         font-weight:600;margin-top:4px;">
              <?= $c['icon'] ?> <?= $c['lbl'] ?>
            </span>
          <?php endif; ?>
        </td>
        <td>
          <form method="POST" action="/vetclinic/admin/appointments.php" class="inline-form">
            <input type="hidden" name="action"  value="update_status">
            <input type="hidden" name="appt_id" value="<?= (int)$row['id'] ?>">
            <select name="status" class="select-sm">
              <?php foreach (['new'=>'Новая','confirmed'=>'Подтверждена','cancelled'=>'Отменена','completed'=>'Завершена'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $row['status']===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Сохранить</button>
          </form>

          <?php if (!empty($row['form_id'])): ?>
            <div style="margin-top:6px;">
              <?= renderFormToggleButton((int)$row['form_id']) ?>
              <?= renderFormDetails($db, (int)$row['id']) ?>
            </div>
          <?php else: ?>
            <small class="text-muted" style="display:block;margin-top:4px;">📋 Анкета не заполнена</small>
          <?php endif; ?>

          <?php if ($row['comment']): ?><small class="text-muted" style="display:block;margin-top:4px;">💬 <?= e($row['comment']) ?></small><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Пагинация -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php if ($currentPage > 1): ?>
    <a href="<?= pageUrl($currentPage - 1) ?>" class="page-btn">← Назад</a>
  <?php endif; ?>

  <?php
  $start = max(1, $currentPage - 2);
  $end   = min($totalPages, $currentPage + 2);
  if ($start > 1): ?><span class="page-dots">1</span><span class="page-dots">…</span><?php endif;
  for ($i = $start; $i <= $end; $i++):
  ?>
    <a href="<?= pageUrl($i) ?>"
       class="page-btn <?= $i === $currentPage ? 'active' : '' ?>">
      <?= $i ?>
    </a>
  <?php endfor;
  if ($end < $totalPages): ?><span class="page-dots">…</span><span class="page-dots"><?= $totalPages ?></span><?php endif; ?>

  <?php if ($currentPage < $totalPages): ?>
    <a href="<?= pageUrl($currentPage + 1) ?>" class="page-btn">Вперёд →</a>
  <?php endif; ?>

  <span class="page-info">
    Страница <?= $currentPage ?> из <?= $totalPages ?>
    (всего <?= $totalRows ?> записей)
  </span>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
