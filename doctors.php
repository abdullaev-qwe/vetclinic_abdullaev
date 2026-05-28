<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/includes/icons.php';

$specs = cacheGet('doctors_specs', 300, function() use ($db) {
    return $db->query(
        "SELECT DISTINCT specialty FROM doctors WHERE is_active=1 ORDER BY specialty ASC"
    )->fetch_all(MYSQLI_ASSOC);
});

$filterSpec = trim($_GET['specialty'] ?? '');

if ($filterSpec !== '') {
    $stmt = $db->prepare(
        "SELECT * FROM doctors WHERE is_active=1 AND specialty=? ORDER BY name ASC"
    );
    $stmt->bind_param('s', $filterSpec);
    $stmt->execute();
    $doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $doctors = cacheGet('doctors_active', 300, function() use ($db) {
        return $db->query(
            "SELECT * FROM doctors WHERE is_active=1 ORDER BY name ASC"
        )->fetch_all(MYSQLI_ASSOC);
    });
}

// Иконки по специализации — вместо случайных фото чужих людей
$specIcons = [
    'Терапевт'    => 'stethoscope',
    'Хирург'      => 'scissors',
    'Дерматолог'  => 'droplets',
    'Стоматолог'  => 'smile',
    'Офтальмолог' => 'eye',
];
// Цвета фона аватара по специализации
$specColors = [
    'Терапевт'    => ['#e8f2ee', '#0d4f3c'],
    'Хирург'      => ['#fdecea', '#8b1a1a'],
    'Дерматолог'  => ['#e3f2fd', '#0d47a1'],
    'Стоматолог'  => ['#fdf6e3', '#8a6500'],
    'Офтальмолог' => ['#f3e8fd', '#5b1a8b'],
];

$pageTitle = 'Врачи';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
  <div class="container">
    <h1>Наши специалисты</h1>
    <p>Команда опытных ветеринарных врачей, которым можно доверять</p>
  </div>
</div>

<section class="page-section">
  <div class="container">

    <!-- Фильтр по специализации -->
    <div class="filter-tabs" style="margin-bottom:44px;">
      <a href="/vetclinic/doctors.php"
         class="filter-tab <?= $filterSpec===''?'active':'' ?>">
        Все специалисты
      </a>
      <?php foreach ($specs as $sp): ?>
        <a href="/vetclinic/doctors.php?specialty=<?= urlencode($sp['specialty']) ?>"
           class="filter-tab <?= $filterSpec===$sp['specialty']?'active':'' ?>">
          <?= e($sp['specialty']) ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if (empty($doctors)): ?>
      <div class="empty-state"><p>Специалисты не найдены.</p></div>
    <?php else: ?>

      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;">
        <?php foreach ($doctors as $i => $doc): ?>
          <?php
          $spec   = $doc['specialty'];
          $icon   = $specIcons[$spec]   ?? 'stethoscope';
          $colors = $specColors[$spec]  ?? ['#e8f2ee','#0d4f3c'];
          $bg     = $colors[0];
          $fg     = $colors[1];
          // Инициалы врача
          $nameParts = explode(' ', $doc['name']);
          $initials  = '';
          foreach ($nameParts as $part) {
              $initials .= mb_strtoupper(mb_substr($part, 0, 1));
              if (mb_strlen($initials) >= 2) break;
          }
          ?>
          <div style="background:var(--white);border-radius:var(--radius-xl);
                      overflow:hidden;border:1px solid var(--border);
                      box-shadow:var(--shadow-xs);transition:var(--t-mid);
                      display:flex;flex-direction:column;"
               class="reveal">

            <!-- Аватар: большой цветной блок с иконкой и инициалами -->
            <div style="height:220px;background:<?= $bg ?>;
                        display:flex;flex-direction:column;
                        align-items:center;justify-content:center;
                        gap:12px;position:relative;overflow:hidden;">

              <!-- Декоративные круги на фоне -->
              <div style="position:absolute;top:-30px;right:-30px;width:140px;height:140px;
                          border-radius:50%;background:<?= $fg ?>;opacity:.06;"></div>
              <div style="position:absolute;bottom:-20px;left:-20px;width:100px;height:100px;
                          border-radius:50%;background:<?= $fg ?>;opacity:.06;"></div>

              <!-- Большой круг с инициалами -->
              <div style="width:90px;height:90px;border-radius:50%;
                          background:<?= $fg ?>;
                          display:flex;align-items:center;justify-content:center;
                          box-shadow:0 8px 24px <?= $fg ?>44;
                          position:relative;z-index:1;">
                <span style="font-family:var(--font-display);font-size:2rem;
                              font-weight:700;color:white;line-height:1;">
                  <?= e($initials) ?>
                </span>
              </div>

              <!-- Иконка специализации -->
              <div style="color:<?= $fg ?>;opacity:.5;position:relative;z-index:1;">
                <?= icon($icon, 20) ?>
              </div>

              <!-- Бейдж специализации -->
              <div style="position:absolute;bottom:14px;left:50%;transform:translateX(-50%);
                          background:<?= $fg ?>;color:white;
                          border-radius:20px;padding:4px 16px;
                          font-size:.7rem;font-weight:700;
                          letter-spacing:.07em;text-transform:uppercase;
                          white-space:nowrap;z-index:1;">
                <?= e($spec) ?>
              </div>
            </div>

            <!-- Информация -->
            <div style="padding:22px 22px 24px;display:flex;flex-direction:column;flex:1;">
              <h3 style="font-size:1rem;font-weight:700;color:var(--ink);
                          margin-bottom:8px;line-height:1.35;">
                <?= e($doc['name']) ?>
              </h3>

              <div style="display:flex;align-items:center;gap:6px;
                          color:var(--ink-muted);font-size:.84rem;margin-bottom:14px;">
                <?= icon('award', 14) ?>
                <span>Опыт <?= (int)$doc['experience'] ?> лет</span>
              </div>

              <?php if ($doc['bio']): ?>
                <p style="color:var(--ink-soft);font-size:.86rem;line-height:1.7;
                           margin-bottom:18px;flex:1;">
                  <?= e(mb_strimwidth($doc['bio'], 0, 110, '…')) ?>
                </p>
              <?php else: ?>
                <div style="flex:1;"></div>
              <?php endif; ?>

              <a href="/vetclinic/appointment_new.php?doctor_id=<?= (int)$doc['id'] ?>"
                 class="btn btn-outline btn-sm"
                 style="width:100%;justify-content:center;">
                <?= icon('calendar', 14) ?> Записаться
              </a>
            </div>

          </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </div>
</section>

<section class="cta-section">
  <div class="container">
    <h2>Нужна консультация?</h2>
    <p>Запишитесь к нужному специалисту онлайн — быстро и удобно</p>
    <div class="cta-actions">
      <a href="/vetclinic/appointment_new.php" class="btn btn-gold">
        <?= icon('calendar', 18) ?> Записаться на приём
      </a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
