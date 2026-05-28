<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/includes/icons.php';

$services = cacheGet('services_active', 300, function() use ($db) {
    return $db->query("SELECT * FROM services WHERE is_active = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
});

$serviceIcons = [
    'stethoscope','syringe','microscope','scissors',
    'smile','eye','scan','pill','droplets','activity','flask','heart-pulse'
];

$pageTitle = 'Услуги';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
  <div class="container">
    <h1>Наши услуги</h1>
    <p>Полный спектр ветеринарной помощи для вашего питомца</p>
  </div>
</div>

<section class="page-section">
  <div class="container">
    <?php if (empty($services)): ?>
      <div class="empty-state"><p>Услуги временно недоступны.</p></div>
    <?php else: ?>
      <div class="services-grid">
        <?php foreach ($services as $i => $svc): ?>
          <div class="service-card">
            <span class="service-icon">
              <?= icon($serviceIcons[$i % count($serviceIcons)], 28) ?>
            </span>
            <h3><?= e($svc['name']) ?></h3>
            <p><?= e($svc['description'] ?? '') ?></p>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;">
              <div class="service-price">
                <?= number_format((float)$svc['price'], 0, '.', ' ') ?> ₽
              </div>
              <div style="color:var(--ink-muted);font-size:.8rem;display:flex;align-items:center;gap:4px;">
                <?= icon('clock', 14) ?> <?= (int)$svc['duration'] ?> мин
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div style="text-align:center;margin-top:48px;">
      <a href="/vetclinic/appointment_new.php" class="btn btn-gold">
        <?= icon('calendar', 18) ?> Записаться на приём
      </a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
