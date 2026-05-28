<?php
/**
 * mailer.php — отправка email-уведомлений
 *
 * Поддерживает два режима:
 * 1. SMTP через Mailtrap (для тестирования) — настройка в config/mail.php
 * 2. Учебный режим — пишет в logs/emails.log (по умолчанию)
 */

class Mailer
{
    private array $config;
    private string $logFile;

    public function __construct()
    {
        $configFile = __DIR__ . '/../config/mail.php';
        $this->config = file_exists($configFile) ? require $configFile : [
            'mode'      => 'log',          // 'smtp' или 'log'
            'from_name' => 'ВетЗабота',
            'from_addr' => 'noreply@vetcare.local',
        ];
        $this->logFile = __DIR__ . '/../logs/emails.log';

        // Создаём папку логов если нет
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }

    /**
     * Отправить email.
     *
     * @param string $to       Email получателя
     * @param string $subject  Тема
     * @param string $htmlBody HTML тело письма
     * @return bool
     */
    public function send(string $to, string $subject, string $htmlBody): bool
    {
        if ($this->config['mode'] === 'smtp') {
            return $this->sendSmtp($to, $subject, $htmlBody);
        }
        return $this->logEmail($to, $subject, $htmlBody);
    }

    /**
     * Учебный режим: писать в файл логов
     */
    private function logEmail(string $to, string $subject, string $htmlBody): bool
    {
        $log  = "═══════════════════════════════════════════════════════\n";
        $log .= "[" . date('Y-m-d H:i:s') . "] EMAIL\n";
        $log .= "Кому:   $to\n";
        $log .= "Тема:   $subject\n";
        $log .= "───────────────────────────────────────────────────────\n";
        $log .= strip_tags($htmlBody) . "\n\n";

        return file_put_contents($this->logFile, $log, FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * SMTP через сокет (без сторонних библиотек).
     * Поддерживает Mailtrap (smtp.mailtrap.io) и подобные.
     */
    private function sendSmtp(string $to, string $subject, string $htmlBody): bool
    {
        $host = $this->config['smtp_host'] ?? '';
        $port = $this->config['smtp_port'] ?? 2525;
        $user = $this->config['smtp_user'] ?? '';
        $pass = $this->config['smtp_pass'] ?? '';
        $from = $this->config['from_addr'];
        $fromName = $this->config['from_name'];

        try {
            // Подключаемся к SMTP
            $socket = @fsockopen($host, $port, $errno, $errstr, 10);
            if (!$socket) {
                $this->logEmail($to, "[SMTP ERROR] $subject", "Connection failed: $errstr");
                return false;
            }

            // Хелпер для работы с SMTP
            $send = function(string $cmd) use ($socket) {
                fwrite($socket, $cmd . "\r\n");
                return fgets($socket, 512);
            };

            fgets($socket, 512); // Приветствие сервера
            $send("EHLO localhost");
            $send("AUTH LOGIN");
            $send(base64_encode($user));
            $send(base64_encode($pass));
            $send("MAIL FROM:<$from>");
            $send("RCPT TO:<$to>");
            $send("DATA");

            $message  = "From: $fromName <$from>\r\n";
            $message .= "To: $to\r\n";
            $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $message .= $htmlBody . "\r\n.";

            fwrite($socket, $message . "\r\n");
            fgets($socket, 512);
            $send("QUIT");
            fclose($socket);

            // Логируем для отладки
            $this->logEmail($to, "[SMTP OK] $subject", $htmlBody);
            return true;

        } catch (\Throwable $e) {
            $this->logEmail($to, "[SMTP EXCEPTION] $subject", $e->getMessage());
            return false;
        }
    }
}

/**
 * Шаблоны писем — единый дизайн в стиле VetCare
 */
class EmailTemplates
{
    public static function wrap(string $title, string $body): string
    {
        return '<!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:20px;background:#faf8f4;font-family:Georgia,serif;">
<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.08);">
  <div style="background:#0d4f3c;color:#c9a84c;padding:24px;text-align:center;">
    <h1 style="margin:0;font-size:24px;">🐾 ВетЗабота</h1>
    <p style="margin:6px 0 0;opacity:0.85;font-size:13px;">Ветеринарная клиника</p>
  </div>
  <div style="padding:30px;color:#0f1f1a;line-height:1.6;">
    <h2 style="color:#0d4f3c;font-size:20px;margin-top:0;">' . $title . '</h2>
    ' . $body . '
  </div>
  <div style="background:#faf8f4;padding:16px;text-align:center;color:#5a7068;font-size:12px;border-top:1px solid #d8e6e0;">
    Это автоматическое уведомление с сайта ВетЗабота.<br>
    Если у вас есть вопросы — свяжитесь с нами по телефону.
  </div>
</div>
</body>
</html>';
    }

    /** Письмо при регистрации */
    public static function welcome(string $userName): string
    {
        $body = '<p>Здравствуйте, <strong>' . htmlspecialchars($userName) . '</strong>!</p>
<p>Спасибо за регистрацию в ветеринарной клинике ВетЗабота. Теперь вы можете:</p>
<ul style="padding-left:20px;line-height:1.8;">
  <li>Записываться на приём онлайн</li>
  <li>Просматривать историю посещений</li>
  <li>Оставлять отзывы о наших врачах</li>
</ul>
<p style="margin-top:24px;text-align:center;">
  <a href="http://localhost/vetclinic/profile.php"
     style="display:inline-block;background:#0d4f3c;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;">
    Перейти в личный кабинет
  </a>
</p>';
        return self::wrap('Добро пожаловать!', $body);
    }

    /** Подтверждение записи */
    public static function appointmentCreated(array $data): string
    {
        $date = date('d.m.Y', strtotime($data['date']));
        $body = '<p>Здравствуйте, <strong>' . htmlspecialchars($data['user_name']) . '</strong>!</p>
<p>Ваша запись на приём успешно создана. Ожидайте подтверждения в течение часа.</p>
<table style="width:100%;border-collapse:collapse;margin-top:16px;background:#f0f7f4;border-radius:8px;overflow:hidden;">
  <tr><td style="padding:12px;border-bottom:1px solid #d8e6e0;color:#5a7068;width:40%;">Дата:</td>
      <td style="padding:12px;border-bottom:1px solid #d8e6e0;font-weight:600;">' . $date . ' в ' . htmlspecialchars($data['time']) . '</td></tr>
  <tr><td style="padding:12px;border-bottom:1px solid #d8e6e0;color:#5a7068;">Врач:</td>
      <td style="padding:12px;border-bottom:1px solid #d8e6e0;font-weight:600;">' . htmlspecialchars($data['doctor']) . '</td></tr>
  <tr><td style="padding:12px;border-bottom:1px solid #d8e6e0;color:#5a7068;">Услуга:</td>
      <td style="padding:12px;border-bottom:1px solid #d8e6e0;font-weight:600;">' . htmlspecialchars($data['service']) . '</td></tr>
  <tr><td style="padding:12px;color:#5a7068;">Питомец:</td>
      <td style="padding:12px;font-weight:600;">' . htmlspecialchars($data['pet_name']) . ' (' . htmlspecialchars($data['pet_type']) . ')</td></tr>
</table>
<p style="margin-top:20px;color:#5a7068;font-size:13px;">
  Если планы изменились — отмените запись в личном кабинете не позднее, чем за 2 часа до приёма.
</p>';
        return self::wrap('Запись создана', $body);
    }

    /** Изменение статуса записи */
    public static function appointmentStatusChanged(array $data): string
    {
        $statusLabels = [
            'confirmed' => '✅ подтверждена',
            'cancelled' => '❌ отменена',
            'completed' => '✔️ завершена',
        ];
        $statusText = $statusLabels[$data['status']] ?? $data['status'];
        $date = date('d.m.Y', strtotime($data['date']));

        $body = '<p>Здравствуйте, <strong>' . htmlspecialchars($data['user_name']) . '</strong>!</p>
<p>Статус вашей записи изменился: <strong>' . $statusText . '</strong></p>
<table style="width:100%;border-collapse:collapse;margin-top:16px;background:#f0f7f4;border-radius:8px;overflow:hidden;">
  <tr><td style="padding:12px;border-bottom:1px solid #d8e6e0;color:#5a7068;width:40%;">Дата:</td>
      <td style="padding:12px;border-bottom:1px solid #d8e6e0;font-weight:600;">' . $date . ' в ' . htmlspecialchars($data['time']) . '</td></tr>
  <tr><td style="padding:12px;border-bottom:1px solid #d8e6e0;color:#5a7068;">Врач:</td>
      <td style="padding:12px;border-bottom:1px solid #d8e6e0;font-weight:600;">' . htmlspecialchars($data['doctor']) . '</td></tr>
  <tr><td style="padding:12px;color:#5a7068;">Услуга:</td>
      <td style="padding:12px;font-weight:600;">' . htmlspecialchars($data['service']) . '</td></tr>
</table>';
        return self::wrap('Изменение статуса записи', $body);
    }
}

/**
 * Хелпер-функции для использования в проекте
 */
function sendWelcomeEmail(string $email, string $name): bool {
    $mailer = new Mailer();
    return $mailer->send($email, '🐾 Добро пожаловать в ВетЗаботу!', EmailTemplates::welcome($name));
}

function sendAppointmentCreatedEmail(string $email, array $data): bool {
    $mailer = new Mailer();
    return $mailer->send($email, '✅ Запись на приём создана', EmailTemplates::appointmentCreated($data));
}

function sendAppointmentStatusEmail(string $email, array $data): bool {
    $mailer = new Mailer();
    return $mailer->send($email, '📋 Изменение статуса записи', EmailTemplates::appointmentStatusChanged($data));
}
