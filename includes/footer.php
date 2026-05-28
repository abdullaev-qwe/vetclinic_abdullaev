<?php // includes/footer.php ?>
</main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">

      <div class="footer-col">
        <a href="/vetclinic/index.php" class="logo logo-light">
          <svg width="42" height="42" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0;display:block;">
            <path d="M8 6 L36 6 Q40 6 40 10 L40 26 Q40 38 22 43 Q4 38 4 26 L4 10 Q4 6 8 6 Z"
                  fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="1.5"/>
            <rect x="18.5" y="14" width="7" height="18" rx="2" fill="white" opacity="0.9"/>
            <rect x="13" y="19.5" width="18" height="7" rx="2" fill="white" opacity="0.9"/>
            <circle cx="15" cy="9"  r="3.5" fill="#c9a84c"/>
            <circle cx="22" cy="6"  r="3"   fill="#c9a84c"/>
            <circle cx="29" cy="9"  r="3.5" fill="#c9a84c"/>
          </svg>
          <div class="logo-text">Вет<strong>Забота</strong><em> клиника</em></div>
        </a>
        <p>Современный ветеринарный центр, где каждый питомец — особенный пациент. Работаем с 2010 года.</p>
      </div>

      <div class="footer-col">
        <h4>Навигация</h4>
        <ul>
          <li><a href="/vetclinic/about.php">О клинике</a></li>
          <li><a href="/vetclinic/services.php">Услуги</a></li>
          <li><a href="/vetclinic/doctors.php">Врачи</a></li>
          <li><a href="/vetclinic/prices.php">Цены</a></li>
          <li><a href="/vetclinic/contacts.php">Контакты</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Пациентам</h4>
        <ul>
          <li><a href="/vetclinic/appointment_new.php">Записаться</a></li>
          <li><a href="/vetclinic/profile.php">Личный кабинет</a></li>
          <li><a href="/vetclinic/appointments.php">Мои записи</a></li>
          <li><a href="/vetclinic/register.php">Регистрация</a></li>
          <li><a href="/vetclinic/login.php">Войти</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Контакты</h4>
        <ul class="contacts-list">
          <li><span><?= icon('map-pin', 16) ?></span><span>г. Москва, ул. Ветеринарная, д. 15</span></li>
          <li><span><?= icon('phone', 16) ?></span><a href="tel:+74951234567">+7 (495) 123-45-67</a></li>
          <li><span><?= icon('mail', 16) ?></span><a href="mailto:info@vetcare.ru">info@vetcare.ru</a></li>
          <li><span><?= icon('clock', 16) ?></span><span>Пн–Пт: 8:00–20:00</span></li>
          <li><span><?= icon('clock', 16) ?></span><span>Сб–Вс: 9:00–18:00</span></li>
        </ul>
      </div>

    </div>

    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> ВетЗабота. Все права защищены.</p>
      <p>Сделано с ❤️ для ваших питомцев</p>
    </div>
  </div>
</footer>

<?php
// Передаём nonce в JS-файл через data-атрибут тега script.
// Это позволяет CSP разрешить выполнение именно нашего скрипта.
$nonce = isset($cspNonce) ? e($cspNonce) : '';
?>
<script src="/vetclinic/assets/js/main.js" nonce="<?= $nonce ?>"></script>
  <script src="/vetclinic/assets/js/avatar.js?v=1777190581" nonce="<?= e($cspNonce ?? '') ?>"></script>
  <script nonce="<?= e($cspNonce ?? "") ?>" src="/vetclinic/assets/js/theme.js"></script>
  <script src="/vetclinic/assets/js/skeleton.js?v=1777127200" nonce="<?= e($cspNonce ?? '') ?>"></script>

<?php include __DIR__ . '/chat_widget.php'; ?>
</body>
</html>
