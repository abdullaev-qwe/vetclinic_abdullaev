<?php
/**
 * config/mail.php — настройки отправки email
 *
 * РЕЖИМЫ:
 * - 'log'  — учебный режим: пишет письма в logs/emails.log (по умолчанию)
 * - 'smtp' — реальная отправка через SMTP (Mailtrap, Gmail и т.д.)
 *
 * ── Как настроить Mailtrap ──
 * 1. Зарегистрируйтесь на mailtrap.io (бесплатно)
 * 2. Создайте Inbox → вкладка SMTP Settings
 * 3. Скопируйте host, port, username, password сюда
 * 4. Поменяйте 'mode' на 'smtp'
 * 5. Письма будут приходить в ваш ящик на mailtrap.io
 */

return [
    // Режим работы
    'mode'      => 'log',  // 'log' | 'smtp'

    // От кого
    'from_name' => 'ВетЗабота',
    'from_addr' => 'noreply@vetcare.local',

    // SMTP настройки (если mode = 'smtp')
    'smtp_host' => 'sandbox.smtp.mailtrap.io',
    'smtp_port' => 2525,
    'smtp_user' => 'YOUR_MAILTRAP_USERNAME',
    'smtp_pass' => 'YOUR_MAILTRAP_PASSWORD',
];
