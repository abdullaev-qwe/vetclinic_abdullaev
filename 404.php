<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

http_response_code(404);
$pageTitle = 'Страница не найдена';
require_once __DIR__ . '/includes/header.php';
?>

<section style="min-height:60vh;display:flex;align-items:center;padding:80px 0;">
  <div class="container" style="text-align:center;">

    <div style="font-family:var(--font-display);font-size:8rem;font-weight:700;
                color:var(--emerald-pale);line-height:1;margin-bottom:24px;">
      404
    </div>

    <div style="color:var(--emerald);margin-bottom:20px;">
      <?= icon('map-pin', 48) ?>
    </div>

    <h1 style="font-family:var(--font-display);font-size:2.2rem;font-weight:700;
               margin-bottom:14px;letter-spacing:-.02em;">
      Страница не найдена
    </h1>

    <p style="color:var(--ink-muted);font-size:1rem;max-width:440px;
              margin:0 auto 36px;line-height:1.75;">
      Возможно, страница была удалена, переименована или по этому адресу ничего нет.
      Проверьте правильность ссылки или вернитесь на главную.
    </p>

    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
      <a href="/vetclinic/index.php" class="btn btn-primary">
        На главную
      </a>
      <a href="/vetclinic/services.php" class="btn btn-outline">
        Наши услуги
      </a>
      <a href="/vetclinic/contacts.php" class="btn btn-ghost">
        Контакты
      </a>
    </div>

  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
