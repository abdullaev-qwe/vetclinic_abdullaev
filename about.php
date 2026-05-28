<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
$pageTitle = 'О клинике';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
  <div class="container">
    <h1>О клинике ВетЗабота</h1>
    <p>Современный ветеринарный центр, где каждый питомец — особенный пациент</p>
  </div>
</div>

<section class="page-section">
  <div class="container">

    <!-- История с фото -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center;margin-bottom:80px;">
      <div>
        <div class="section-label" style="margin-bottom:16px;">Наша история</div>
        <h2 style="font-family:var(--font-display);font-size:2.1rem;margin-bottom:20px;letter-spacing:-.02em;">
          Работаем с 2010 года
        </h2>
        <p style="color:var(--ink-soft);margin-bottom:16px;line-height:1.82;">
          Клиника ВетЗабота открылась с одной простой идеей — обеспечить домашним животным ту же качественную медицинскую помощь, что и людям. За эти годы мы выросли из небольшого кабинета в полноценный многопрофильный центр.
        </p>
        <p style="color:var(--ink-soft);margin-bottom:16px;line-height:1.82;">
          Сегодня наша команда включает терапевтов, хирургов, дерматологов, стоматологов и офтальмологов. Принимаем кошек, собак, грызунов и экзотических животных.
        </p>
        <p style="color:var(--ink-soft);line-height:1.82;">
          Мы постоянно инвестируем в оборудование и обучение персонала, чтобы оставаться на передовой ветеринарной медицины.
        </p>
      </div>
      <div style="position:relative;">
        <img src="https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?w=700&h=500&q=80&auto=format&fit=crop"
             alt="Ветеринарная клиника ВетЗабота"
             style="width:100%;border-radius:var(--radius-xl);box-shadow:var(--shadow-lg);"
             loading="lazy">
        <div style="position:absolute;bottom:-20px;left:-20px;background:var(--emerald);
                    color:white;border-radius:var(--radius-lg);padding:20px 24px;
                    box-shadow:var(--shadow);">
          <div style="font-family:var(--font-display);font-size:2rem;font-weight:700;line-height:1;">15+</div>
          <div style="font-size:.78rem;opacity:.8;margin-top:2px;">лет работы</div>
        </div>
      </div>
    </div>

    <!-- Цифры -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:80px;text-align:center;">
      <?php
      $nums = [
        ['15+','лет на рынке','trending-up'],
        ['5','специалистов','users'],
        ['3000+','пациентов','heart-pulse'],
        ['12','услуг','sparkles'],
      ];
      foreach ($nums as $n):
      ?>
        <div style="background:var(--white);border-radius:var(--radius-lg);padding:28px 20px;
                    border:1px solid var(--border);box-shadow:var(--shadow-xs);" class="reveal">
          <div style="color:var(--emerald);margin-bottom:10px;display:flex;justify-content:center;">
            <?= icon($n[2],28) ?>
          </div>
          <div style="font-family:var(--font-display);font-size:2.1rem;font-weight:700;color:var(--emerald);margin-bottom:4px;">
            <?= $n[0] ?>
          </div>
          <div style="color:var(--ink-muted);font-size:.78rem;text-transform:uppercase;letter-spacing:.07em;">
            <?= $n[1] ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Галерея клиники -->
    <div class="section-title">
      <div class="section-label">Фотогалерея</div>
      <h2>Наша <em>клиника</em></h2>
      <p>Современное оборудование и уютная атмосфера для ваших питомцев</p>
    </div>
    <div class="gallery-grid" style="margin-bottom:80px;">
      <!-- Большое фото: ветеринар с животным -->
      <div class="gallery-item gallery-item--large">
        <img src="https://images.unsplash.com/photo-1628009368231-7bb7cfcb0def?w=800&h=600&q=80&auto=format&fit=crop"
             alt="Ветеринар осматривает кошку" loading="lazy">
      </div>
      <!-- Кошка -->
      <div class="gallery-item">
        <img src="https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=400&h=280&q=80&auto=format&fit=crop"
             alt="Кошка" loading="lazy">
      </div>
      <!-- Собака -->
      <div class="gallery-item">
        <img src="https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=400&h=280&q=80&auto=format&fit=crop"
             alt="Собака" loading="lazy">
      </div>
      <!-- Оборудование -->
      <div class="gallery-item">
        <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=400&h=280&q=80&auto=format&fit=crop"
             alt="Оборудование" loading="lazy">
      </div>
      <!-- Врач -->
      <div class="gallery-item">
        <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=400&h=280&q=80&auto=format&fit=crop"
             alt="Врач" loading="lazy">
      </div>
    </div>

    <!-- Ценности -->
    <div class="section-title">
      <div class="section-label">Наши принципы</div>
      <h2>Ценности, на которых <em>мы строим работу</em></h2>
    </div>
    <div class="features-grid">
      <div class="feature-card reveal"><div class="feature-icon"><?= icon('heart-pulse',26) ?></div><h3>Забота</h3><p>Каждое животное заслуживает внимания, тепла и профессиональной помощи</p></div>
      <div class="feature-card reveal reveal-delay-1"><div class="feature-icon"><?= icon('microscope',26) ?></div><h3>Профессионализм</h3><p>Наши врачи постоянно учатся и применяют доказательную медицину</p></div>
      <div class="feature-card reveal reveal-delay-2"><div class="feature-icon"><?= icon('shield-check',26) ?></div><h3>Честность</h3><p>Объясняем диагноз, план лечения и стоимость — без скрытых платежей</p></div>
      <div class="feature-card reveal reveal-delay-3"><div class="feature-icon"><?= icon('zap',26) ?></div><h3>Оперативность</h3><p>Экстренные случаи принимаем без записи. Онлайн-запись доступна 24/7</p></div>
    </div>

    <!-- Оборудование -->
    <div style="margin-top:72px;">
      <div class="section-title">
        <div class="section-label">Оснащение</div>
        <h2>Наше <em>оборудование</em></h2>
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:18px;">
        <?php
        $equip = [
          ['scan','УЗИ-аппарат','Ультразвуковая диагностика органов брюшной полости и сердца'],
          ['activity','Рентген','Цифровой рентгеновский аппарат для исследования скелета и внутренних органов'],
          ['flask','Лаборатория','Собственная лаборатория для общих и биохимических анализов крови'],
          ['eye','Офтальмоскоп','Профессиональное оборудование для диагностики болезней глаз'],
          ['smile','Стоматология','Специализированное оборудование для чистки и лечения зубов'],
          ['heart-pulse','Анестезиология','Безопасное проведение операций под общей анестезией'],
        ];
        foreach ($equip as $eq):
        ?>
          <div style="background:var(--white);border-radius:var(--radius-lg);padding:24px;
                      border:1px solid var(--border);display:flex;gap:16px;align-items:flex-start;" class="reveal">
            <div style="width:44px;height:44px;background:var(--emerald-ghost);border-radius:var(--radius-sm);
                        display:flex;align-items:center;justify-content:center;color:var(--emerald);flex-shrink:0;">
              <?= icon($eq[0],22) ?>
            </div>
            <div>
              <strong style="display:block;margin-bottom:4px;font-size:.93rem;"><?= $eq[1] ?></strong>
              <span style="color:var(--ink-muted);font-size:.84rem;line-height:1.6;"><?= $eq[2] ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</section>

<section class="cta-section">
  <div class="container">
    <h2>Хотите познакомиться с командой?</h2>
    <p>Посмотрите профили наших врачей и запишитесь к нужному специалисту</p>
    <div class="cta-actions">
      <a href="/vetclinic/doctors.php"         class="btn btn-white">Наши врачи</a>
      <a href="/vetclinic/appointment_new.php" class="btn-outline-white">Записаться</a>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
