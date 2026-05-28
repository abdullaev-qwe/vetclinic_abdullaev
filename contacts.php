<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$errors = []; $success = false;
$form   = ['name'=>'','email'=>'','message'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $form['name']    = trim($_POST['name']    ?? '');
    $form['email']   = trim($_POST['email']   ?? '');
    $form['message'] = trim($_POST['message'] ?? '');
    if (mb_strlen($form['name']) < 2)                        $errors[] = 'Введите ваше имя.';
    if (!filter_var($form['email'],FILTER_VALIDATE_EMAIL))   $errors[] = 'Введите корректный email.';
    if (mb_strlen($form['message']) < 10)                    $errors[] = 'Сообщение слишком короткое.';
    if (empty($errors)) { $success = true; $form = ['name'=>'','email'=>'','message'=>'']; }
}

$pageTitle = 'Контакты';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
  <div class="container">
    <h1>Контакты</h1>
    <p>Мы всегда рады помочь вам и вашему питомцу</p>
  </div>
</div>

<section class="page-section">
  <div class="container">
    <div class="contacts-grid">

      <div>
        <!-- Контактная информация -->
        <div class="contact-info-card" style="margin-bottom:24px;">
          <h2>Как нас найти</h2>
          <div class="contact-item">
            <div class="contact-item-icon"><?= icon('map-pin',20) ?></div>
            <div class="contact-item-text"><strong>Адрес</strong>г. Москва, ул. Ветеринарная, д. 15</div>
          </div>
          <div class="contact-item">
            <div class="contact-item-icon"><?= icon('phone',20) ?></div>
            <div class="contact-item-text"><strong>Телефон</strong><a href="tel:+74951234567">+7 (495) 123-45-67</a></div>
          </div>
          <div class="contact-item">
            <div class="contact-item-icon"><?= icon('mail',20) ?></div>
            <div class="contact-item-text"><strong>Email</strong><a href="mailto:info@vetcare.ru">info@vetcare.ru</a></div>
          </div>
          <div class="contact-item">
            <div class="contact-item-icon"><?= icon('clock',20) ?></div>
            <div class="contact-item-text"><strong>Режим работы</strong>Пн–Пт: 8:00–20:00<br>Сб–Вс: 9:00–18:00</div>
          </div>
        </div>

        <!-- Форма -->
        <div class="contact-info-card">
          <h2>Написать нам</h2>
          <?php if ($success): ?>
            <div class="alert alert-success"><?= icon('check-circle',18) ?> Сообщение отправлено!</div>
          <?php endif; ?>
          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
          <?php endif; ?>
          <form method="POST" action="/vetclinic/contacts.php" novalidate>
            <?= csrfField() ?>
            <div class="form-group">
              <label>Имя *</label>
              <input type="text" name="name" value="<?= e($form['name']) ?>" placeholder="Иван Петров" required maxlength="100">
            </div>
            <div class="form-group">
              <label>Email *</label>
              <input type="email" name="email" value="<?= e($form['email']) ?>" placeholder="ivan@example.com" required>
            </div>
            <div class="form-group">
              <label>Сообщение *</label>
              <textarea name="message" rows="4" placeholder="Ваш вопрос..." required><?= e($form['message']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-full">
              <?= icon('mail',17) ?> Отправить
            </button>
          </form>
        </div>
      </div>

      <div>
        <!-- Карта OpenStreetMap -->
        <div class="map-frame" style="margin-bottom:20px;">
          <iframe
            src="https://www.openstreetmap.org/export/embed.html?bbox=37.5800%2C55.7300%2C37.6600%2C55.7700&layer=mapnik&marker=55.751244%2C37.618423"
            title="Карта ВетЗабота"
            allowfullscreen
            loading="lazy">
          </iframe>
        </div>

        <a href="https://www.openstreetmap.org/?mlat=55.751244&mlon=37.618423#map=15/55.7512/37.6184"
           target="_blank"
           style="display:block;text-align:center;font-size:.82rem;color:var(--ink-muted);margin-bottom:20px;">
          Открыть на карте →
        </a>

        <!-- Как добраться -->
        <div style="background:var(--white);border-radius:var(--radius-xl);padding:28px;
                    border:1px solid var(--border);box-shadow:var(--shadow-xs);">
          <h3 style="font-size:.95rem;font-weight:700;margin-bottom:18px;display:flex;align-items:center;gap:8px;">
            <?= icon('map-pin',18) ?> Как добраться
          </h3>
          <ul style="display:flex;flex-direction:column;gap:12px;">
            <li style="display:flex;gap:12px;">
              <span style="color:var(--emerald);flex-shrink:0;"><?= icon('activity',17) ?></span>
              <span style="color:var(--ink-soft);font-size:.88rem;">Метро «Ветеринарная» — 5 минут пешком</span>
            </li>
            <li style="display:flex;gap:12px;">
              <span style="color:var(--emerald);flex-shrink:0;"><?= icon('trending-up',17) ?></span>
              <span style="color:var(--ink-soft);font-size:.88rem;">Автобусы 14, 27, 38 — остановка «Ветеринарная»</span>
            </li>
            <li style="display:flex;gap:12px;">
              <span style="color:var(--emerald);flex-shrink:0;"><?= icon('shield-check',17) ?></span>
              <span style="color:var(--ink-soft);font-size:.88rem;">Бесплатная парковка рядом с клиникой</span>
            </li>
          </ul>
        </div>
      </div>

    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
