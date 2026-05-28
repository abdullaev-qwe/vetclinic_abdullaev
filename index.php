<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/cache.php';
require_once __DIR__ . '/includes/icons.php';

$servicesRaw = cacheGet('home_services', 300, function() use ($db) {
    return $db->query(
        "SELECT * FROM services WHERE is_active=1 ORDER BY id ASC LIMIT 6"
    )->fetch_all(MYSQLI_ASSOC);
});

$doctorsRaw = cacheGet('home_doctors', 300, function() use ($db) {
    return $db->query(
        "SELECT * FROM doctors WHERE is_active=1 ORDER BY id ASC LIMIT 4"
    )->fetch_all(MYSQLI_ASSOC);
});

$reviews = $db->query(
    "SELECT r.*, u.name AS user_name, d.name AS doctor_name
     FROM reviews r JOIN users u ON r.user_id=u.id
     LEFT JOIN doctors d ON r.doctor_id=d.id
     WHERE r.is_visible=1 ORDER BY r.created_at DESC LIMIT 3"
)->fetch_all(MYSQLI_ASSOC);

$serviceIcons = [
    'stethoscope','syringe','microscope','scissors',
    'smile','eye','scan','pill','droplets','activity','flask','heart-pulse'
];

// Стабильные фото врачей
$doctorPhotos = [
    'https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=400&h=400&q=80&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=400&h=400&q=80&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1594824476967-48c8b964273f?w=400&h=400&q=80&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1537368910025-700350fe46c7?w=400&h=400&q=80&auto=format&fit=crop',
];
$doctorIcons = ['stethoscope','scissors','eye','smile'];

$pageTitle = 'Главная';

// Cache-buster для новых ассетов главной (Этап 1 «вау»-редизайна)
$wowCssAbs = __DIR__ . '/assets/css/home-wow.css';
$wowJsAbs  = __DIR__ . '/assets/js/home-wow.js';
$wowCssV   = is_file($wowCssAbs) ? filemtime($wowCssAbs) : 1;
$wowJsV    = is_file($wowJsAbs)  ? filemtime($wowJsAbs)  : 1;

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="/vetclinic/assets/css/home-wow.css?v=<?= e((string)$wowCssV) ?>">

<div class="wow-home">

<!-- ══ HERO 2.0 ══ -->
<section class="wow-hero" id="hero">
  <!-- Mesh-градиент (3 слоя цветных пятен) -->
  <div class="wow-hero__mesh" aria-hidden="true"><span></span></div>

  <!-- Декоративные «лапки» -->
  <div class="wow-hero__paws" aria-hidden="true">
    <svg viewBox="0 0 64 64"><path d="M22 20a8 8 0 11-16 0 8 8 0 0116 0zm36 0a8 8 0 11-16 0 8 8 0 0116 0zM14 38a7 7 0 11-14 0 7 7 0 0114 0zm50 0a7 7 0 11-14 0 7 7 0 0114 0zM32 64c-10 0-18-7-18-15 0-9 8-17 18-17s18 8 18 17c0 8-8 15-18 15z"/></svg>
    <svg viewBox="0 0 64 64"><path d="M22 20a8 8 0 11-16 0 8 8 0 0116 0zm36 0a8 8 0 11-16 0 8 8 0 0116 0zM14 38a7 7 0 11-14 0 7 7 0 0114 0zm50 0a7 7 0 11-14 0 7 7 0 0114 0zM32 64c-10 0-18-7-18-15 0-9 8-17 18-17s18 8 18 17c0 8-8 15-18 15z"/></svg>
    <svg viewBox="0 0 64 64"><path d="M22 20a8 8 0 11-16 0 8 8 0 0116 0zm36 0a8 8 0 11-16 0 8 8 0 0116 0zM14 38a7 7 0 11-14 0 7 7 0 0114 0zm50 0a7 7 0 11-14 0 7 7 0 0114 0zM32 64c-10 0-18-7-18-15 0-9 8-17 18-17s18 8 18 17c0 8-8 15-18 15z"/></svg>
    <svg viewBox="0 0 64 64"><path d="M22 20a8 8 0 11-16 0 8 8 0 0116 0zm36 0a8 8 0 11-16 0 8 8 0 0116 0zM14 38a7 7 0 11-14 0 7 7 0 0114 0zm50 0a7 7 0 11-14 0 7 7 0 0114 0zM32 64c-10 0-18-7-18-15 0-9 8-17 18-17s18 8 18 17c0 8-8 15-18 15z"/></svg>
    <svg viewBox="0 0 64 64"><path d="M22 20a8 8 0 11-16 0 8 8 0 0116 0zm36 0a8 8 0 11-16 0 8 8 0 0116 0zM14 38a7 7 0 11-14 0 7 7 0 0114 0zm50 0a7 7 0 11-14 0 7 7 0 0114 0zM32 64c-10 0-18-7-18-15 0-9 8-17 18-17s18 8 18 17c0 8-8 15-18 15z"/></svg>
  </div>

  <div class="container">
    <div class="wow-hero__inner">

      <!-- Левая колонка: текст, кнопки, статистика -->
      <div class="wow-hero__copy">
        <div class="wow-hero__label wow-reveal">Ветеринарная клиника · Москва</div>

        <h1 class="wow-hero__title wow-reveal" data-delay="1">
          Здоровье вашего<br>
          питомца — <em>наш</em><br>
          <span class="accent">главный приоритет</span>
        </h1>

        <p class="wow-hero__lead wow-reveal" data-delay="2">
          ВетЗабота — современный многопрофильный ветеринарный центр.
          Опытные специалисты, передовое оборудование и атмосфера заботы для каждого пациента.
        </p>

        <div class="wow-hero__cta wow-reveal" data-delay="3">
          <a href="/vetclinic/appointment_new.php" class="wow-btn-magnet">
            <?= icon('calendar', 18) ?>
            <span>Записаться на приём</span>
          </a>
          <a href="/vetclinic/services.php" class="wow-btn-ghost">
            Наши услуги <?= icon('arrow-right', 16) ?>
          </a>
        </div>

        <div class="wow-hero__stats wow-reveal" data-delay="4">
          <div class="wow-stat">
            <strong data-count="15" data-suffix="+">15+</strong>
            <span>лет работы</span>
          </div>
          <div class="wow-stat">
            <strong data-count="5">5</strong>
            <span>специалистов</span>
          </div>
          <div class="wow-stat">
            <strong data-count="3000" data-suffix="+">3000+</strong>
            <span>пациентов</span>
          </div>
          <div class="wow-stat">
            <strong data-count="12">12</strong>
            <span>услуг</span>
          </div>
        </div>
      </div>

      <!-- Правая колонка: парящие фото + бейджи -->
      <div class="wow-hero__art wow-reveal" data-delay="2">
        <div class="wow-hero__photo wow-hero__photo--main">
          <img src="https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=700&h=900&q=80&auto=format&fit=crop"
               alt="Счастливый питомец" loading="eager">
        </div>
        <div class="wow-hero__photo wow-hero__photo--mini1">
          <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=500&h=350&q=80&auto=format&fit=crop"
               alt="Ветеринарный врач" loading="eager">
        </div>
        <div class="wow-hero__photo wow-hero__photo--mini2">
          <img src="https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=400&h=280&q=80&auto=format&fit=crop"
               alt="Кошка" loading="eager">
        </div>

        <div class="wow-hero__badge wow-hero__badge--tl">
          <div class="wow-hero__badge-icn" style="color:var(--emerald);">
            <?= icon('check-circle', 22) ?>
          </div>
          <div>
            <strong>Онлайн-запись</strong>
            <span>Без очередей</span>
          </div>
        </div>

        <div class="wow-hero__badge wow-hero__badge--br">
          <div class="wow-hero__badge-icn" style="color:var(--gold);">
            <?= icon('star', 20) ?>
          </div>
          <div>
            <strong>Рейтинг 4.9/5</strong>
            <span>По отзывам клиентов</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ══ MARQUEE доверия ══ -->
<section class="wow-marquee" aria-label="Преимущества клиники">
  <div class="wow-marquee__track">
    <div class="wow-marquee__item"><?= icon('shield-check', 18) ?> Лицензия Росздравнадзора</div>
    <div class="wow-marquee__item"><?= icon('clock', 18) ?> Работаем с 2010 года</div>
    <div class="wow-marquee__item"><?= icon('award', 18) ?> Сертифицированные врачи</div>
    <div class="wow-marquee__item"><?= icon('scan', 18) ?> Собственная лаборатория</div>
    <div class="wow-marquee__item"><?= icon('heart-pulse', 18) ?> Скорая ветеринарная помощь</div>
    <div class="wow-marquee__item"><?= icon('star', 18) ?> Рейтинг 4.9 / 5</div>
    <div class="wow-marquee__item"><?= icon('phone', 18) ?> Поддержка 24/7</div>
  </div>
</section>


<!-- ══ BENTO: Преимущества ══ -->
<section class="wow-section wow-section--cream">
  <div class="container">
    <div class="wow-section__head">
      <div class="wow-section__label">Почему выбирают нас</div>
      <h2 class="wow-section__title">Забота, которой <em>можно доверять</em></h2>
      <p class="wow-section__sub">Мы создаём комфортные условия для лечения и реабилитации ваших любимцев</p>
    </div>

    <div class="wow-bento">
      <!-- Большая «звёздная» плитка с числом -->
      <div class="wow-bento__cell wow-bento__cell--big wow-reveal">
        <div class="wow-bento__icon"><?= icon('award', 28) ?></div>
        <h3>Опытные врачи с многолетним стажем</h3>
        <p>Команда сертифицированных специалистов, регулярно проходящих повышение квалификации в ведущих ветеринарных академиях.</p>
        <div class="wow-bento__big-num">15+</div>
      </div>

      <!-- Высокая плитка-акцент -->
      <div class="wow-bento__cell wow-bento__cell--tall wow-bento__cell--accent wow-reveal" data-delay="1">
        <div class="wow-bento__icon"><?= icon('shield-check', 28) ?></div>
        <h3>Полный цикл лечения</h3>
        <p>От первичного осмотра и диагностики до операций, реабилитации и наблюдения — всё в одном месте, без перенаправлений.</p>
      </div>

      <!-- Широкая плитка -->
      <div class="wow-bento__cell wow-bento__cell--wide wow-reveal" data-delay="2">
        <div class="wow-bento__icon"><?= icon('scan', 28) ?></div>
        <h3>Современное оборудование</h3>
        <p>УЗИ, рентген, биохимический анализатор, собственная лаборатория — точная диагностика на месте за 30 минут.</p>
      </div>

      <!-- Обычная плитка -->
      <div class="wow-bento__cell wow-bento__cell--regular wow-reveal" data-delay="3">
        <div class="wow-bento__icon"><?= icon('calendar', 24) ?></div>
        <h3>Онлайн-запись 24/7</h3>
        <p>Удобное время через личный кабинет — без очередей.</p>
      </div>

      <!-- Малая плитка -->
      <div class="wow-bento__cell wow-bento__cell--small wow-reveal" data-delay="4">
        <div class="wow-bento__icon"><?= icon('heart-pulse', 24) ?></div>
        <h3>Скорая ветпомощь</h3>
        <p>Экстренный приём — приедем или примем без записи.</p>
      </div>
    </div>
  </div>
</section>

<!-- ══ STICKY STORY: Как мы заботимся ══ -->
<section class="wow-story">
  <div class="container">
    <div class="wow-section__head" style="color:#fff;">
      <div class="wow-section__label" style="color:var(--gold);background:rgba(201,168,76,.16);border-color:rgba(201,168,76,.4);">
        Как это работает
      </div>
      <h2 class="wow-section__title" style="color:#fff;">
        Три шага до здоровья <em>вашего питомца</em>
      </h2>
    </div>

    <div class="wow-story__inner">

      <!-- Sticky-визуал: фото меняется в зависимости от активного шага -->
      <div class="wow-story__sticky">
        <div class="wow-story__visual">
          <div class="wow-story__counter">01</div>
          <img class="is-active"
               src="https://images.unsplash.com/photo-1581888227599-779811939961?w=900&h=900&q=80&auto=format&fit=crop"
               alt="Онлайн-запись">
          <img src="https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?w=900&h=900&q=80&auto=format&fit=crop"
               alt="Приём у врача">
          <img src="https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=900&h=900&q=80&auto=format&fit=crop"
               alt="Восстановление">
        </div>
      </div>

      <!-- Шаги -->
      <div class="wow-story__steps">
        <article class="wow-story__step is-active" data-step="0">
          <div class="wow-story__step-num">Шаг 01 · Запись</div>
          <h3>Записываетесь онлайн за минуту</h3>
          <p>Выбираете врача, удобное время и услугу. Подтверждение приходит на email, напоминание — за час до приёма. Можно отменить или перенести в личном кабинете.</p>
        </article>

        <article class="wow-story__step" data-step="1">
          <div class="wow-story__step-num">Шаг 02 · Приём</div>
          <h3>Приходите — без очередей</h3>
          <p>Принимаем строго по времени. Врач осматривает питомца, делает диагностику на месте — УЗИ, анализы, рентген — и составляет план лечения. Все назначения вы видите в кабинете.</p>
        </article>

        <article class="wow-story__step" data-step="2">
          <div class="wow-story__step-num">Шаг 03 · Забота</div>
          <h3>Ведём питомца до полного восстановления</h3>
          <p>Лекарства, контрольные осмотры, диета, реабилитация — всё в одном месте. Связь с врачом через чат поддержки. Никуда не нужно бегать.</p>
        </article>
      </div>

    </div>
  </div>
</section>

<!-- ══ BENTO: Услуги ══ -->
<section class="wow-section">
  <div class="container">
    <div class="wow-section__head">
      <div class="wow-section__label">Что мы лечим</div>
      <h2 class="wow-section__title">Наши <em>услуги</em></h2>
      <p class="wow-section__sub">Полный спектр ветеринарной помощи для кошек, собак и других животных</p>
    </div>

    <div class="wow-bento">
      <?php
      // Распределяем услуги по «нестандартным» bento-плиткам.
      // Первая — большая, вторая — высокая-акцент, остальные — обычные.
      $bentoLayouts = ['big', 'tall wow-bento__cell--accent', 'regular', 'regular', 'wide', 'small'];
      foreach ($servicesRaw as $i => $svc):
        $layout = $bentoLayouts[$i] ?? 'regular';
      ?>
        <div class="wow-bento__cell wow-bento__cell--<?= $layout ?> wow-reveal" data-delay="<?= min($i + 1, 5) ?>">
          <div class="wow-bento__icon"><?= icon($serviceIcons[$i % count($serviceIcons)], 28) ?></div>
          <h3><?= e($svc['name']) ?></h3>
          <p><?= e(mb_strimwidth($svc['description'] ?? '', 0, 110, '…')) ?></p>
          <div class="wow-bento__big-num"><?= number_format((float)$svc['price'], 0, '.', ' ') ?> ₽</div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="text-align:center;margin-top:48px;">
      <a href="/vetclinic/services.php" class="wow-btn-ghost" style="border-color:rgba(13,79,60,.3);color:var(--emerald);background:transparent;">
        Все услуги и цены <?= icon('arrow-right', 16) ?>
      </a>
    </div>
  </div>
</section>

<!-- ══ ВРАЧИ ══ -->
<section class="section" style="background:var(--white);">
  <div class="container">
    <div class="section-title">
      <div class="section-label">Наша команда</div>
      <h2>Специалисты, которым <em>доверяют</em></h2>
      <p>Профессионалы в области ветеринарной медицины с многолетним опытом</p>
    </div>
    <div class="doctors-grid">
      <?php
      $specColors = [
        'Терапевт'    => ['#e8f2ee','#0d4f3c'],
        'Хирург'      => ['#fdecea','#8b1a1a'],
        'Дерматолог'  => ['#e3f2fd','#0d47a1'],
        'Стоматолог'  => ['#fdf6e3','#8a6500'],
        'Офтальмолог' => ['#f3e8fd','#5b1a8b'],
      ];
      $specIcons = [
        'Терапевт'    => 'stethoscope',
        'Хирург'      => 'scissors',
        'Дерматолог'  => 'droplets',
        'Стоматолог'  => 'smile',
        'Офтальмолог' => 'eye',
      ];
      foreach ($doctorsRaw as $doc):
        $spec   = $doc['specialty'];
        $colors = $specColors[$spec] ?? ['#e8f2ee','#0d4f3c'];
        $icn    = $specIcons[$spec]  ?? 'stethoscope';
        $parts  = explode(' ', $doc['name']);
        $init   = '';
        foreach ($parts as $p) { $init .= mb_strtoupper(mb_substr($p,0,1)); if(mb_strlen($init)>=2)break; }
      ?>
        <div class="doctor-card">
          <!-- Аватар с инициалами вместо фото -->
          <div class="doctor-photo" style="background:<?= $colors[0] ?>;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;position:relative;overflow:hidden;">
            <div style="position:absolute;top:-20px;right:-20px;width:100px;height:100px;border-radius:50%;background:<?= $colors[1] ?>;opacity:.07;"></div>
            <div style="position:absolute;bottom:-15px;left:-15px;width:80px;height:80px;border-radius:50%;background:<?= $colors[1] ?>;opacity:.07;"></div>
            <div style="width:80px;height:80px;border-radius:50%;background:<?= $colors[1] ?>;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 20px <?= $colors[1] ?>44;position:relative;z-index:1;">
              <span style="font-family:var(--font-display);font-size:1.8rem;font-weight:700;color:white;line-height:1;"><?= e($init) ?></span>
            </div>
            <div style="color:<?= $colors[1] ?>;opacity:.4;position:relative;z-index:1;"><?= icon($icn,18) ?></div>
          </div>
          <div class="doctor-card-body">
            <h3><?= e($doc['name']) ?></h3>
            <div class="doctor-specialty"><?= e($doc['specialty']) ?></div>
            <div class="doctor-exp"><?= icon('award',14) ?> Опыт <?= (int)$doc['experience'] ?> лет</div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:44px;">
      <a href="/vetclinic/doctors.php" class="btn btn-outline">
        Все врачи <?= icon('arrow-right', 16) ?>
      </a>
    </div>
  </div>
</section>

<!-- ══ ГАЛЕРЕЯ ══ -->
<section class="section">
  <div class="container">
    <div class="section-title">
      <div class="section-label">Наша клиника</div>
      <h2>Современное <em>пространство</em></h2>
      <p>Комфортная атмосфера для пациентов и их владельцев</p>
    </div>
    <div class="gallery-grid">
      <div class="gallery-item gallery-item--large">
        <img src="https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=800&h=700&q=80&auto=format&fit=crop"
             alt="Ветеринарная клиника" loading="lazy">
      </div>
      <div class="gallery-item">
        <img src="https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?w=400&h=300&q=80&auto=format&fit=crop"
             alt="Осмотр кошки" loading="lazy">
      </div>
      <div class="gallery-item">
        <img src="https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=400&h=300&q=80&auto=format&fit=crop"
             alt="Собака" loading="lazy">
      </div>
      <div class="gallery-item">
        <img src="https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=400&h=300&q=80&auto=format&fit=crop"
             alt="Кошка" loading="lazy">
      </div>
      <div class="gallery-item">
        <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=400&h=300&q=80&auto=format&fit=crop"
             alt="Ветеринарный врач" loading="lazy">
      </div>
    </div>
  </div>
</section>

<!-- ══ ОТЗЫВЫ ══ -->
<?php if (!empty($reviews)): ?>
<section class="section" style="background:var(--white);">
  <div class="container">
    <div class="section-title">
      <div class="section-label">Отзывы</div>
      <h2>Нам <em>доверяют</em></h2>
      <p>Тысячи владельцев домашних животных выбирают ВетЗаботу</p>
    </div>
    <div class="reviews-grid">
      <?php foreach ($reviews as $rev): ?>
        <div class="review-card">
          <div class="review-stars" style="display:flex;gap:3px;margin-bottom:14px;">
            <?php for ($s = 1; $s <= 5; $s++): ?>
              <span style="color:<?= $s <= (int)$rev['rating'] ? 'var(--gold)' : 'var(--border)' ?>">
                <?= icon('star', 15) ?>
              </span>
            <?php endfor; ?>
          </div>
          <div class="review-text"><?= e($rev['review_text']) ?></div>
          <div class="review-author"><?= e($rev['user_name']) ?></div>
          <?php if ($rev['doctor_name']): ?>
            <div class="review-doctor">Врач: <?= e($rev['doctor_name']) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══ CTA ══ -->
<section class="cta-section">
  <div class="container">
    <h2>Запишитесь на приём сегодня</h2>
    <p>Не откладывайте здоровье питомца на потом.<br>Онлайн-запись займёт меньше двух минут.</p>
    <div class="cta-actions">
      <a href="/vetclinic/appointment_new.php" class="btn btn-gold">
        <?= icon('calendar', 18) ?> Записаться онлайн
      </a>
      <a href="/vetclinic/contacts.php" class="btn-outline-white">
        <?= icon('phone', 16) ?> Связаться с нами
      </a>
    </div>
  </div>
</section>

</div><!-- /.wow-home -->

<script src="/vetclinic/assets/js/home-wow.js?v=<?= e((string)$wowJsV) ?>"
        nonce="<?= e($cspNonce ?? '') ?>" defer></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
