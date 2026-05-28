<?php
require_once __DIR__ . '/includes/admin_header.php';

$adminPageTitle = 'Дашборд';

// ── Статистика ──
$r = $db->query("SELECT COUNT(*) AS cnt FROM users");
$stats['users'] = (int)$r->fetch_assoc()['cnt'];

$r = $db->query("SELECT COUNT(*) AS cnt FROM doctors WHERE is_active = 1");
$stats['doctors'] = (int)$r->fetch_assoc()['cnt'];

$r = $db->query("SELECT COUNT(*) AS cnt FROM services WHERE is_active = 1");
$stats['services'] = (int)$r->fetch_assoc()['cnt'];

$r = $db->query("SELECT COUNT(*) AS cnt FROM appointments");
$stats['appointments'] = (int)$r->fetch_assoc()['cnt'];

$r = $db->query("SELECT COUNT(*) AS cnt FROM appointments WHERE status = 'new'");
$stats['new'] = (int)$r->fetch_assoc()['cnt'];

$r = $db->query("SELECT COUNT(*) AS cnt FROM appointments WHERE status = 'confirmed'");
$stats['confirmed'] = (int)$r->fetch_assoc()['cnt'];

// ── Записи за последние 14 дней ──
$dailyData = [];
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dailyData[$date] = 0;
}
$res = $db->query(
    "SELECT DATE(appointment_date) AS d, COUNT(*) AS cnt
     FROM appointments
     WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
     GROUP BY DATE(appointment_date)"
);
while ($row = $res->fetch_assoc()) {
    if (isset($dailyData[$row['d']])) {
        $dailyData[$row['d']] = (int)$row['cnt'];
    }
}

$chartLabels = [];
$chartValues = array_values($dailyData);
foreach (array_keys($dailyData) as $d) {
    $chartLabels[] = date('d.m', strtotime($d));
}

// ── Топ-5 услуг ──
$topServices = $db->query(
    "SELECT s.name, COUNT(a.id) AS cnt
     FROM services s
     LEFT JOIN appointments a ON s.id = a.service_id
     GROUP BY s.id, s.name
     ORDER BY cnt DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

// ── Статусы ──
$statusData = [];
$statusLabels = ['new'=>'Новые','confirmed'=>'Подтверждённые','cancelled'=>'Отменённые','completed'=>'Завершённые'];
foreach ($statusLabels as $key => $label) {
    $r = $db->prepare("SELECT COUNT(*) AS c FROM appointments WHERE status = ?");
    $r->bind_param('s', $key);
    $r->execute();
    $statusData[$label] = (int)$r->get_result()->fetch_assoc()['c'];
    $r->close();
}

// ── Нагрузка врачей ──
$doctorLoad = $db->query(
    "SELECT d.name, COUNT(a.id) AS cnt
     FROM doctors d
     LEFT JOIN appointments a ON d.id = a.doctor_id
     WHERE d.is_active = 1
     GROUP BY d.id, d.name
     ORDER BY cnt DESC"
)->fetch_all(MYSQLI_ASSOC);

// ── Последние записи ──
$recent = $db->query(
    "SELECT * FROM view_all_appointments ORDER BY created_at DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);
?>

<!-- Фикс высоты canvas — КРИТИЧНО для Chart.js -->
<style nonce="<?= e($cspNonce ?? '') ?>">
.chart-wrapper {
    position: relative;
    height: 280px;
    width: 100%;
}
.chart-wrapper-tall {
    position: relative;
    height: 320px;
    width: 100%;
}
.chart-wrapper canvas,
.chart-wrapper-tall canvas {
    max-height: 100% !important;
    width: 100% !important;
}
.charts-row {
    display: grid;
    gap: 20px;
    margin-bottom: 24px;
}
.charts-row-2 { grid-template-columns: 2fr 1fr; }
.charts-row-1 { grid-template-columns: 1fr 1fr; }

@media (max-width: 900px) {
    .charts-row-2,
    .charts-row-1 { grid-template-columns: 1fr; }
}
</style>

<!-- Карточки статистики -->
<div class="stats-cards">
  <div class="stat-card-admin">
    <div><div class="stat-num"><?= $stats['users'] ?></div>
    <div class="stat-lbl">Пользователей</div></div>
  </div>
  <div class="stat-card-admin">
    <div><div class="stat-num"><?= $stats['appointments'] ?></div>
    <div class="stat-lbl">Всего записей</div></div>
  </div>
  <div class="stat-card-admin stat-card-alert">
    <div><div class="stat-num"><?= $stats['new'] ?></div>
    <div class="stat-lbl">Новых записей</div></div>
  </div>
  <div class="stat-card-admin stat-card-ok">
    <div><div class="stat-num"><?= $stats['confirmed'] ?></div>
    <div class="stat-lbl">Подтверждённых</div></div>
  </div>
  <div class="stat-card-admin">
    <div><div class="stat-num"><?= $stats['doctors'] ?></div>
    <div class="stat-lbl">Активных врачей</div></div>
  </div>
  <div class="stat-card-admin">
    <div><div class="stat-num"><?= $stats['services'] ?></div>
    <div class="stat-lbl">Активных услуг</div></div>
  </div>
</div>

<!-- Ряд 1: линия + пирог -->
<div class="charts-row charts-row-2">
  <div class="admin-section">
    <div class="admin-section-header"><h2>Динамика записей за 14 дней</h2></div>
    <div class="chart-wrapper"><canvas id="chartDaily"></canvas></div>
  </div>
  <div class="admin-section">
    <div class="admin-section-header"><h2>Статусы записей</h2></div>
    <div class="chart-wrapper"><canvas id="chartStatus"></canvas></div>
  </div>
</div>

<!-- Ряд 2: услуги + врачи -->
<div class="charts-row charts-row-1">
  <div class="admin-section">
    <div class="admin-section-header"><h2>Топ-5 популярных услуг</h2></div>
    <div class="chart-wrapper-tall"><canvas id="chartServices"></canvas></div>
  </div>
  <div class="admin-section">
    <div class="admin-section-header"><h2>Нагрузка врачей</h2></div>
    <div class="chart-wrapper-tall"><canvas id="chartDoctors"></canvas></div>
  </div>
</div>

<!-- Последние записи -->
<div class="admin-section">
  <div class="admin-section-header">
    <h2>Последние записи на приём</h2>
    <a href="/vetclinic/admin/appointments.php" class="btn btn-outline btn-sm">Все записи</a>
  </div>
  <table class="admin-table">
    <thead>
      <tr><th>#</th><th>Дата / Время</th><th>Пользователь</th>
      <th>Врач</th><th>Услуга</th><th>Статус</th></tr>
    </thead>
    <tbody>
      <?php foreach ($recent as $row): ?>
        <tr>
          <td><?= (int)$row['id'] ?></td>
          <td><?= formatDate($row['appointment_date']) ?>
          <small><?= formatTime($row['appointment_time']) ?></small></td>
          <td><?= e($row['user_name']) ?></td>
          <td><?= e($row['doctor_name']) ?></td>
          <td><?= e($row['service_name']) ?></td>
          <td><span class="badge <?= statusBadgeClass($row['status']) ?>">
            <?= statusLabel($row['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script nonce="<?= e($cspNonce ?? '') ?>" src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script nonce="<?= e($cspNonce ?? '') ?>">
// Ждём полной загрузки Chart.js
window.addEventListener('load', function() {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js не загрузился');
        return;
    }

    const EMERALD = '#0d4f3c';
    const GOLD    = '#c9a84c';

    Chart.defaults.font.family = 'DM Sans, system-ui, sans-serif';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;

    // ── 1. Линия: записи за 14 дней ──
    new Chart(document.getElementById('chartDaily'), {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Количество записей',
                data: <?= json_encode($chartValues) ?>,
                borderColor: EMERALD,
                backgroundColor: 'rgba(13,79,60,0.1)',
                fill: true,
                tension: 0.35,
                pointBackgroundColor: EMERALD,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 600 },
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: EMERALD, padding: 10, cornerRadius: 8 }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
                x: { grid: { display: false } }
            }
        }
    });

    // ── 2. Пирог: статусы ──
    new Chart(document.getElementById('chartStatus'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($statusData), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                data: <?= json_encode(array_values($statusData)) ?>,
                backgroundColor: ['#f39c12', '#27ae60', '#e74c3c', '#3498db'],
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 600 },
            plugins: {
                legend: { position: 'bottom', labels: { padding: 10, font: { size: 11 } } }
            }
        }
    });

    // ── 3. Столбцы: топ услуг ──
    new Chart(document.getElementById('chartServices'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($topServices, 'name'), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Записей',
                data: <?= json_encode(array_map('intval', array_column($topServices, 'cnt'))) ?>,
                backgroundColor: GOLD,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 600 },
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
                x: { grid: { display: false } }
            }
        }
    });

    // ── 4. Горизонтальные столбцы: врачи ──
    new Chart(document.getElementById('chartDoctors'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($doctorLoad, 'name'), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Записей',
                data: <?= json_encode(array_map('intval', array_column($doctorLoad, 'cnt'))) ?>,
                backgroundColor: EMERALD,
                borderRadius: 6,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 600 },
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
                y: { grid: { display: false } }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
