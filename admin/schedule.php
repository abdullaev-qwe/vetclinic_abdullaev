<?php
require_once __DIR__ . '/includes/admin_auth.php';

$adminPageTitle = 'Расписание врачей';

// Сохранение расписания
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_schedule') {
    $doctorId = (int)($_POST['doctor_id'] ?? 0);
    if ($doctorId > 0) {
        // Удаляем старое расписание врача
        $stmt = $db->prepare("DELETE FROM doctor_schedule WHERE doctor_id = ?");
        $stmt->bind_param('i', $doctorId);
        $stmt->execute();
        $stmt->close();

        // Записываем новое
        for ($day = 1; $day <= 7; $day++) {
            $isWorking = isset($_POST['working'][$day]) ? 1 : 0;
            $timeStart = $_POST['start'][$day] ?? '09:00';
            $timeEnd   = $_POST['end'][$day]   ?? '18:00';

            $stmt = $db->prepare(
                "INSERT INTO doctor_schedule (doctor_id, day_of_week, time_start, time_end, is_working)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('iissi', $doctorId, $day, $timeStart, $timeEnd, $isWorking);
            $stmt->execute();
            $stmt->close();
        }

        logAdminAction($db, 'Обновлено расписание врача #' . $doctorId);
        setFlash('success', 'Расписание сохранено.');
        redirect('/vetclinic/admin/schedule.php?doctor_id=' . $doctorId);
    }
}

// Выбранный врач
$selectedDoctorId = (int)($_GET['doctor_id'] ?? 0);
$doctors = getAllDoctors($db);

// Расписание выбранного врача
$currentSchedule = [];
if ($selectedDoctorId > 0) {
    $stmt = $db->prepare("SELECT * FROM doctor_schedule WHERE doctor_id = ? ORDER BY day_of_week ASC");
    $stmt->bind_param('i', $selectedDoctorId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as $r) {
        $currentSchedule[$r['day_of_week']] = $r;
    }
}

$days = [1=>'Понедельник', 2=>'Вторник', 3=>'Среда', 4=>'Четверг', 5=>'Пятница', 6=>'Суббота', 7=>'Воскресенье'];

// Стандартные слоты времени
$timeSlots = [];
for ($h = 7; $h <= 21; $h++) {
    $timeSlots[] = sprintf('%02d:00', $h);
    $timeSlots[] = sprintf('%02d:30', $h);
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<div style="display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start;">

  <!-- Список врачей -->
  <div class="admin-form-panel">
    <h2>Выберите врача</h2>
    <div style="display:flex;flex-direction:column;gap:6px;">
      <?php foreach ($doctors as $doc): ?>
        <a href="?doctor_id=<?= (int)$doc['id'] ?>"
           style="display:flex;align-items:center;gap:12px;padding:12px 14px;
                  border-radius:var(--radius-sm);border:1.5px solid <?= $selectedDoctorId===(int)$doc['id']?'var(--emerald)':'var(--border)' ?>;
                  background:<?= $selectedDoctorId===(int)$doc['id']?'var(--emerald-ghost)':'var(--white)' ?>;
                  text-decoration:none;transition:var(--t-fast);">
          <div style="width:36px;height:36px;border-radius:50%;
                      background:<?= $selectedDoctorId===(int)$doc['id']?'var(--emerald)':'var(--emerald-ghost)' ?>;
                      display:flex;align-items:center;justify-content:center;
                      color:<?= $selectedDoctorId===(int)$doc['id']?'white':'var(--emerald)' ?>;
                      font-family:var(--font-display);font-weight:700;font-size:1rem;flex-shrink:0;">
            <?= mb_strtoupper(mb_substr($doc['name'], 0, 1)) ?>
          </div>
          <div>
            <div style="font-weight:700;font-size:.86rem;color:var(--ink);"><?= e($doc['name']) ?></div>
            <div style="font-size:.75rem;color:var(--ink-muted);"><?= e($doc['specialty']) ?></div>
          </div>
          <?php if (!$doc['is_active']): ?>
            <span class="badge badge-cancelled" style="margin-left:auto;font-size:.65rem;">Неактивен</span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Форма расписания -->
  <div class="admin-table-panel">
    <?php if ($selectedDoctorId <= 0): ?>
      <div style="text-align:center;padding:48px 24px;color:var(--ink-muted);">
        <?php
        // Находим иконку
        echo '<div style="margin-bottom:16px;color:var(--emerald-pale);">';
        // inline SVG calendar
        echo '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>';
        echo '</div>';
        ?>
        <p style="font-size:.95rem;">Выберите врача слева для управления расписанием</p>
      </div>

    <?php else: ?>
      <?php
      $selDoc = null;
      foreach ($doctors as $d) {
          if ((int)$d['id'] === $selectedDoctorId) { $selDoc = $d; break; }
      }
      ?>
      <h2>Расписание: <?= $selDoc ? e($selDoc['name']) : '' ?></h2>

      <form method="POST" action="/vetclinic/admin/schedule.php">
        <input type="hidden" name="action"    value="save_schedule">
        <input type="hidden" name="doctor_id" value="<?= $selectedDoctorId ?>">

        <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px;">
          <?php foreach ($days as $dow => $dayName): ?>
            <?php
            $slot      = $currentSchedule[$dow] ?? null;
            $isWorking = $slot ? (bool)$slot['is_working'] : ($dow <= 5);
            $start     = $slot ? substr($slot['time_start'],0,5) : '09:00';
            $end       = $slot ? substr($slot['time_end'],0,5)   : '18:00';
            $isWeekend = ($dow >= 6);
            ?>
            <div style="display:grid;grid-template-columns:28px 140px 1fr 1fr;
                        align-items:center;gap:14px;padding:14px 16px;
                        border-radius:var(--radius-sm);border:1.5px solid <?= $isWorking?'var(--border)':'var(--border)' ?>;
                        background:<?= $isWorking?'var(--white)':'var(--cream)' ?>;"
                 id="row-<?= $dow ?>">

              <!-- Чекбокс -->
              <input type="checkbox" name="working[<?= $dow ?>]" value="1"
                     id="w<?= $dow ?>"
                     <?= $isWorking ? 'checked' : '' ?>
                     onchange="toggleDay(<?= $dow ?>)"
                     style="width:18px;height:18px;accent-color:var(--emerald);cursor:pointer;">

              <!-- День -->
              <label for="w<?= $dow ?>" style="font-weight:700;font-size:.88rem;cursor:pointer;
                     color:<?= $isWeekend?'var(--gold)':'var(--ink)' ?>;">
                <?= $dayName ?>
                <?php if ($isWeekend): ?><span style="font-size:.72rem;font-weight:400;color:var(--ink-muted);"> (выходной)</span><?php endif; ?>
              </label>

              <!-- Время начала -->
              <div>
                <label style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;
                               color:var(--ink-muted);font-weight:700;display:block;margin-bottom:4px;">
                  Начало
                </label>
                <select name="start[<?= $dow ?>]" id="start<?= $dow ?>"
                        style="padding:7px 10px;border:1.5px solid var(--border);border-radius:var(--radius-sm);
                               font-family:var(--font-body);font-size:.86rem;background:var(--white);
                               <?= !$isWorking ? 'opacity:.4;pointer-events:none;' : '' ?>"
                        <?= !$isWorking ? 'disabled' : '' ?>>
                  <?php foreach ($timeSlots as $t): ?>
                    <option value="<?= $t ?>" <?= $t === $start ? 'selected' : '' ?>><?= $t ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Время конца -->
              <div>
                <label style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;
                               color:var(--ink-muted);font-weight:700;display:block;margin-bottom:4px;">
                  Конец
                </label>
                <select name="end[<?= $dow ?>]" id="end<?= $dow ?>"
                        style="padding:7px 10px;border:1.5px solid var(--border);border-radius:var(--radius-sm);
                               font-family:var(--font-body);font-size:.86rem;background:var(--white);
                               <?= !$isWorking ? 'opacity:.4;pointer-events:none;' : '' ?>"
                        <?= !$isWorking ? 'disabled' : '' ?>>
                  <?php foreach ($timeSlots as $t): ?>
                    <option value="<?= $t ?>" <?= $t === $end ? 'selected' : '' ?>><?= $t ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

            </div>
          <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn-primary">
          Сохранить расписание
        </button>
        <a href="/vetclinic/schedule.php" target="_blank" class="btn btn-ghost" style="margin-left:8px;">
          Посмотреть на сайте →
        </a>
      </form>

      <script>
      function toggleDay(dow) {
          var cb     = document.getElementById('w' + dow);
          var row    = document.getElementById('row-' + dow);
          var startS = document.getElementById('start' + dow);
          var endS   = document.getElementById('end' + dow);
          var on     = cb.checked;
          row.style.background   = on ? 'white' : 'var(--cream)';
          startS.disabled        = !on;
          endS.disabled          = !on;
          startS.style.opacity   = on ? '1' : '0.4';
          endS.style.opacity     = on ? '1' : '0.4';
          startS.style.pointerEvents = on ? '' : 'none';
          endS.style.pointerEvents   = on ? '' : 'none';
      }
      </script>
    <?php endif; ?>
  </div>

</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
