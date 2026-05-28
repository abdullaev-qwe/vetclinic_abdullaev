<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$services = $db->query("SELECT * FROM services WHERE is_active=1 ORDER BY price ASC")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Цены';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
  <div class="container">
    <h1>Цены на услуги</h1>
    <p>Прозрачное ценообразование без скрытых платежей</p>
  </div>
</div>

<section class="page-section">
  <div class="container">
    <div style="max-width:820px;margin:0 auto;">

      <!-- Подсказка -->
      <div style="background:var(--gold-pale);border-radius:var(--radius-lg);padding:20px 24px;
                  margin-bottom:36px;border:1px solid var(--border-gold);display:flex;gap:16px;align-items:flex-start;">
        <div style="color:var(--gold);flex-shrink:0;margin-top:2px;"><?= icon('sparkles',22) ?></div>
        <div>
          <strong style="color:#7a5a10;">Обратите внимание</strong>
          <p style="color:#7a5a10;font-size:.88rem;margin-top:4px;line-height:1.7;opacity:.85;">
            Стоимость может варьироваться в зависимости от сложности случая и веса животного. Точную цену уточняйте у врача.
          </p>
        </div>
      </div>

      <!-- Таблица цен -->
      <?php if (!empty($services)): ?>
        <table class="prices-table" style="margin-bottom:56px;">
          <thead>
            <tr><th>Услуга</th><th>Длительность</th><th>Цена</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($services as $svc): ?>
              <tr>
                <td>
                  <strong><?= e($svc['name']) ?></strong>
                  <?php if ($svc['description']): ?><br><span class="price-duration"><?= e(mb_strimwidth($svc['description'],0,70,'…')) ?></span><?php endif; ?>
                </td>
                <td><span style="display:flex;align-items:center;gap:5px;" class="price-duration"><?= icon('clock',14) ?> <?= (int)$svc['duration'] ?> мин</span></td>
                <td class="price-amount"><?= number_format((float)$svc['price'],0,'.',' ') ?> ₽</td>
                <td>
                  <a href="/vetclinic/appointment_new.php?service_id=<?= (int)$svc['id'] ?>"
                     class="btn btn-sm btn-outline">Записаться</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

    </div>
  </div>
</section>

<!-- ══ КАЛЬКУЛЯТОР СТОИМОСТИ ══ -->
<section class="calculator-section">
  <div class="container">
    <div class="section-title">
      <div class="section-label">Удобно</div>
      <h2>Калькулятор <em>стоимости</em></h2>
      <p>Подберите услугу и узнайте стоимость заранее</p>
    </div>

    <div class="calc-box">
      <div class="form-row" style="margin-bottom:20px;">
        <div class="form-group" style="margin-bottom:0;">
          <label for="calcService">Выберите услугу</label>
          <select id="calcService">
            <option value="0" data-price="0" data-duration="0">— Выберите услугу —</option>
            <?php foreach ($services as $svc): ?>
              <option value="<?= (int)$svc['id'] ?>"
                      data-price="<?= (float)$svc['price'] ?>"
                      data-duration="<?= (int)$svc['duration'] ?>"
                      data-name="<?= e($svc['name']) ?>">
                <?= e($svc['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label for="calcPetType">Вид животного</label>
          <select id="calcPetType">
            <option value="1">Кошка / Кот</option>
            <option value="1">Собака (до 10 кг)</option>
            <option value="1.3">Собака (10–30 кг)</option>
            <option value="1.6">Собака (от 30 кг)</option>
            <option value="0.8">Грызун / Кролик</option>
            <option value="1.2">Птица / Рептилия</option>
          </select>
        </div>
      </div>

      <div id="calcResult" class="calc-result" style="display:none;">
        <div class="calc-result-label">Ориентировочная стоимость</div>
        <div class="calc-result-price" id="calcPrice">0 ₽</div>
        <div class="calc-result-duration" id="calcDuration"></div>
        <div style="margin-top:20px;">
          <a href="/vetclinic/appointment_new.php" class="btn btn-gold">
            <?= icon('calendar',16) ?> Записаться на этот приём
          </a>
        </div>
      </div>

      <div id="calcEmpty" style="text-align:center;padding:28px;color:var(--ink-muted);">
        <?= icon('calculator',32) ?>
        <p style="margin-top:12px;font-size:.9rem;">Выберите услугу для расчёта стоимости</p>
      </div>
    </div>
  </div>
</section>

<script nonce="<?= e($cspNonce ?? '') ?>">
(function() {
    var svcSelect  = document.getElementById('calcService');
    var petSelect  = document.getElementById('calcPetType');
    var result     = document.getElementById('calcResult');
    var empty      = document.getElementById('calcEmpty');
    var priceEl    = document.getElementById('calcPrice');
    var durationEl = document.getElementById('calcDuration');

    function calc() {
        var opt      = svcSelect.options[svcSelect.selectedIndex];
        var basePrice = parseFloat(opt.getAttribute('data-price')) || 0;
        var duration  = parseInt(opt.getAttribute('data-duration')) || 0;
        var petMult   = parseFloat(petSelect.value) || 1;

        if (basePrice === 0) {
            result.style.display = 'none';
            empty.style.display  = 'block';
            return;
        }

        var total = Math.round(basePrice * petMult);
        priceEl.textContent    = total.toLocaleString('ru-RU') + ' ₽';
        durationEl.textContent = 'Длительность приёма: ' + duration + ' минут';
        result.style.display   = 'block';
        empty.style.display    = 'none';
    }

    svcSelect.addEventListener('change', calc);
    petSelect.addEventListener('change', calc);
})();
</script>

<style nonce="<?= e($cspNonce ?? '') ?>">
#calcService, #calcPetType { background: var(--white); }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
