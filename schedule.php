<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

// Все активные врачи с расписанием
$doctors = $db->query("SELECT * FROM doctors WHERE is_active=1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Расписание всех врачей
$scheduleRaw = $db->query(
    "SELECT * FROM doctor_schedule ORDER BY doctor_id ASC, day_of_week ASC"
)->fetch_all(MYSQLI_ASSOC);

// Группируем расписание по doctor_id
$schedule = [];
foreach ($scheduleRaw as $row) {
    $schedule[$row['doctor_id']][$row['day_of_week']] = $row;
}

$days = [1=>'Пн', 2=>'Вт', 3=>'Ср', 4=>'Чт', 5=>'Пт', 6=>'Сб', 7=>'Вс'];

// Определяем сегодняшний день недели (PHP: 0=Вс, 1=Пн...)
$todayDow = (int)date('N'); // 1=Пн ... 7=Вс

// Иконки и цвета по специализации — те же что на странице врачей
$specColors = [
    'Терапевт'    => ['#e8f2ee','#0d4f3c'],
    'Хирург'      => ['#fdecea','#8b1a1a'],
    'Дерматолог'  => ['#e3f2fd','#0d47a1'],
    'Стоматолог'  => ['#fdf6e3','#8a6500'],
    'Офтальмолог' => ['#f3e8fd','#5b1a8b'],
];

$pageTitle = 'Расписание врачей';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
  <div class="container">
    <h1>Расписание врачей</h1>
    <p>Узнайте когда принимает нужный специалист и запишитесь онлайн</p>
  </div>
</div>

<section class="page-section">
  <div class="container">

    <?php if (empty($doctors)): ?>
      <div class="empty-state"><p>Расписание временно недоступно.</p></div>
    <?php else: ?>

      <!-- Легенда -->
      <div style="display:flex;align-items:center;gap:20px;margin-bottom:36px;flex-wrap:wrap;">
        <span style="font-size:.84rem;color:var(--ink-muted);font-weight:600;">Обозначения:</span>
        <div style="display:flex;align-items:center;gap:8px;">
          <div style="width:14px;height:14px;background:var(--emerald);border-radius:3px;"></div>
          <span style="font-size:.82rem;color:var(--ink-soft);">Рабочий день</span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
          <div style="width:14px;height:14px;background:var(--border);border-radius:3px;"></div>
          <span style="font-size:.82rem;color:var(--ink-soft);">Выходной</span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
          <div style="width:14px;height:14px;background:var(--gold);border-radius:3px;"></div>
          <span style="font-size:.82rem;color:var(--ink-soft);">Сегодня</span>
        </div>
      </div>

      <!-- Карточки врачей с расписанием -->
      <div style="display:flex;flex-direction:column;gap:20px;">
        <?php foreach ($doctors as $i => $doc): ?>
          <?php $docSched = $schedule[$doc['id']] ?? []; ?>
          <div style="background:var(--white);border-radius:var(--radius-xl);
                      border:1px solid var(--border);box-shadow:var(--shadow-xs);
                      overflow:hidden;transition:var(--t-fast);"
               class="reveal">

            <div style="display:flex;align-items:center;gap:0;">

              <!-- Аватар + инфо врача -->
              <?php
              $spec   = $doc['specialty'];
              $colors = $specColors[$spec] ?? ['#e8f2ee','#0d4f3c'];
              $parts  = explode(' ', $doc['name']);
              $init   = '';
              foreach ($parts as $p) {
                  $init .= mb_strtoupper(mb_substr($p, 0, 1));
                  if (mb_strlen($init) >= 2) break;
              }
              ?>
              <div style="display:flex;align-items:center;gap:20px;padding:24px 28px;
                          min-width:300px;border-right:1px solid var(--border);flex-shrink:0;">
                <!-- Аватар с инициалами -->
                <div style="width:64px;height:64px;border-radius:50%;flex-shrink:0;
                             background:<?= $colors[1] ?>;
                             display:flex;align-items:center;justify-content:center;
                             box-shadow:0 4px 12px <?= $colors[1] ?>33;">
                  <span style="font-family:var(--font-display);font-size:1.4rem;
                                font-weight:700;color:white;line-height:1;">
                    <?= e($init) ?>
                  </span>
                </div>
                <div>
                  <div style="font-weight:700;font-size:.95rem;color:var(--ink);margin-bottom:3px;">
                    <?= e($doc['name']) ?>
                  </div>
                  <div style="font-size:.75rem;font-weight:700;color:var(--gold);
                               text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">
                    <?= e($doc['specialty']) ?>
                  </div>
                  <a href="/vetclinic/appointment_new.php?doctor_id=<?= (int)$doc['id'] ?>"
                     class="btn btn-sm btn-primary">
                    <?= icon('calendar',13) ?> Записаться
                  </a>
                </div>
              </div>

              <!-- Сетка расписания -->
              <div style="flex:1;padding:20px 28px;">
                <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px;">
                  <?php foreach ($days as $dow => $dayName): ?>
                    <?php
                    $slot      = $docSched[$dow] ?? null;
                    $isWorking = $slot && $slot['is_working'];
                    $isToday   = ($dow === $todayDow);
                    ?>
                    <div style="text-align:center;">
                      <!-- Название дня -->
                      <div style="font-size:.68rem;font-weight:700;letter-spacing:.08em;
                                  text-transform:uppercase;margin-bottom:6px;
                                  color:<?= $isToday ? 'var(--gold)' : 'var(--ink-muted)' ?>;">
                        <?= $dayName ?>
                        <?php if ($isToday): ?>
                          <div style="width:4px;height:4px;background:var(--gold);border-radius:50%;
                                      margin:2px auto 0;"></div>
                        <?php endif; ?>
                      </div>

                      <?php if ($isWorking): ?>
                        <!-- Рабочий слот -->
                        <div style="background:<?= $isToday ? 'var(--gold)' : 'var(--emerald)' ?>;
                                    color:white;border-radius:var(--radius-sm);
                                    padding:8px 4px;font-size:.72rem;font-weight:600;">
                          <?= substr($slot['time_start'],0,5) ?><br>
                          <span style="opacity:.75;font-size:.65rem;">—</span><br>
                          <?= substr($slot['time_end'],0,5) ?>
                        </div>
                      <?php else: ?>
                        <!-- Выходной -->
                        <div style="background:var(--emerald-ghost);color:var(--ink-muted);
                                    border-radius:var(--radius-sm);padding:8px 4px;
                                    font-size:.72rem;font-weight:600;letter-spacing:.04em;">
                          Вых.
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>

                <!-- Статус: принимает сегодня? -->
                <?php
                $todaySlot = $docSched[$todayDow] ?? null;
                $worksToday = $todaySlot && $todaySlot['is_working'];
                ?>
                <div style="margin-top:12px;font-size:.78rem;
                             color:<?= $worksToday ? 'var(--emerald)' : 'var(--ink-muted)' ?>;
                             display:flex;align-items:center;gap:6px;">
                  <div style="width:6px;height:6px;border-radius:50%;
                               background:<?= $worksToday ? 'var(--emerald)' : 'var(--ink-muted)' ?>;
                               <?= $worksToday ? 'box-shadow:0 0 0 3px rgba(13,79,60,.15);' : '' ?>">
                  </div>
                  <?php if ($worksToday): ?>
                    Принимает сегодня: <?= substr($todaySlot['time_start'],0,5) ?> — <?= substr($todaySlot['time_end'],0,5) ?>
                  <?php else: ?>
                    Сегодня не принимает
                  <?php endif; ?>
                </div>
              </div>

            </div>
          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

    <!-- Подсказка -->
    <div style="background:var(--gold-pale);border-radius:var(--radius-lg);
                padding:20px 24px;margin-top:36px;border:1px solid var(--border-gold);
                display:flex;gap:14px;align-items:flex-start;">
      <div style="color:var(--gold);flex-shrink:0;"><?= icon('sparkles',20) ?></div>
      <div>
        <strong style="color:#7a5a10;font-size:.9rem;">Как записаться?</strong>
        <p style="color:#7a5a10;font-size:.85rem;margin-top:4px;line-height:1.65;opacity:.85;">
          Нажмите «Записаться» рядом с нужным врачом или воспользуйтесь
          <a href="/vetclinic/appointment_new.php" style="color:var(--emerald);font-weight:700;">формой онлайн-записи</a>.
          Запись принимается минимум за 1 день.
        </p>
      </div>
    </div>

  </div>
</section>

<section class="cta-section">
  <div class="container">
    <h2>Не нашли удобное время?</h2>
    <p>Позвоните нам и мы подберём оптимальный вариант</p>
    <div class="cta-actions">
      <a href="tel:+74951234567" class="btn btn-gold">
        <?= icon('phone',18) ?> +7 (495) 123-45-67
      </a>
      <a href="/vetclinic/appointment_new.php" class="btn-outline-white">
        Записаться онлайн
      </a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
