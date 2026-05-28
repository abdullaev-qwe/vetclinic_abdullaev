<?php
/**
 * appointment_form.php — функции работы с анкетами
 */

/** Список симптомов для чекбоксов */
function appointmentSymptomsList(): array
{
    return [
        'lethargy'     => 'Вялость, слабость',
        'no_appetite'  => 'Отсутствие аппетита',
        'vomiting'     => 'Рвота',
        'diarrhea'     => 'Диарея',
        'cough'        => 'Кашель / чихание',
        'breathing'    => 'Затруднённое дыхание',
        'limping'      => 'Хромота',
        'itching'      => 'Зуд / расчёсы',
        'hair_loss'    => 'Выпадение шерсти',
        'fever'        => 'Повышенная температура',
        'discharge'    => 'Выделения (глаза/нос/уши)',
        'urine'        => 'Проблемы с мочеиспусканием',
        'thirst'       => 'Сильная жажда',
        'weight_loss'  => 'Потеря веса',
    ];
}

/** Уровни срочности */
function appointmentUrgencyList(): array
{
    return [
        'planned'   => ['label' => 'Плановый осмотр',         'icon' => '🟢', 'desc' => 'Профилактика, нет острых симптомов'],
        'week'      => ['label' => 'В течение недели',        'icon' => '🟡', 'desc' => 'Лёгкие симптомы, но желательно показать врачу'],
        'urgent'    => ['label' => 'Срочно (1-2 дня)',        'icon' => '🟠', 'desc' => 'Заметное ухудшение, требуется быстрая помощь'],
        'emergency' => ['label' => 'Экстренно',               'icon' => '🔴', 'desc' => 'Угроза жизни — позвоните в клинику!'],
    ];
}

/** Типы животных */
function appointmentPetTypes(): array
{
    return ['Собака','Кошка','Птица','Грызун','Рептилия','Экзотическое','Другое'];
}

/** Получить запись для анкеты или null */
function getAppointmentForUser(mysqli $db, int $appointmentId, int $userId): ?array
{
    $stmt = $db->prepare(
        "SELECT a.*, d.name AS doctor_name, s.name AS service_name
         FROM appointments a
         LEFT JOIN doctors d  ON a.doctor_id  = d.id
         LEFT JOIN services s ON a.service_id = s.id
         WHERE a.id = ? AND a.user_id = ? LIMIT 1"
    );
    $stmt->bind_param('ii', $appointmentId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Получить анкету по ID записи */
function getFormByAppointment(mysqli $db, int $appointmentId): ?array
{
    $stmt = $db->prepare(
        "SELECT * FROM appointment_forms WHERE appointment_id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $appointmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/**
 * Валидация и сохранение анкеты.
 * @return array{ok: bool, errors?: string[], form_id?: int}
 */
function saveAppointmentForm(mysqli $db, int $appointmentId, array $data, ?array $file = null): array
{
    $errors = [];

    $complaint = trim($data['complaint'] ?? '');
    if (mb_strlen($complaint) < 5) {
        $errors[] = 'Опишите жалобу подробнее (минимум 5 символов).';
    }
    if (mb_strlen($complaint) > 2000) {
        $errors[] = 'Жалоба слишком длинная (макс. 2000 символов).';
    }

    // Симптомы (чекбоксы)
    $symptomsRaw = $data['symptoms'] ?? [];
    if (!is_array($symptomsRaw)) $symptomsRaw = [];
    $allowedSymptoms = array_keys(appointmentSymptomsList());
    $symptoms = array_filter($symptomsRaw, fn($s) => in_array($s, $allowedSymptoms, true));
    $symptomsCsv = implode(',', $symptoms);

    $symptomsOther = trim($data['symptoms_other'] ?? '');
    if (mb_strlen($symptomsOther) > 250) {
        $errors[] = 'Поле "другие симптомы" слишком длинное (макс. 250 символов).';
    }

    // Возраст
    $petAgeValue = isset($data['pet_age_value']) && $data['pet_age_value'] !== ''
        ? (int)$data['pet_age_value'] : null;
    if ($petAgeValue !== null && ($petAgeValue < 0 || $petAgeValue > 50)) {
        $errors[] = 'Возраст должен быть от 0 до 50.';
    }
    $petAgeUnit = ($data['pet_age_unit'] ?? 'years') === 'months' ? 'months' : 'years';

    // Срочность
    $urgency = $data['urgency'] ?? 'planned';
    if (!array_key_exists($urgency, appointmentUrgencyList())) {
        $urgency = 'planned';
    }

    // Анализы есть/нет
    $hasTests = !empty($data['has_tests']) ? 1 : 0;

    // Файл (опционально)
    $attachmentFile = null;
    $attachmentType = null;

    if ($file && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = uploadAppointmentAttachment($file, $appointmentId);
        if (!$uploadResult['ok']) {
            $errors[] = 'Файл: ' . $uploadResult['error'];
        } else {
            $attachmentFile = $uploadResult['filename'];
            $attachmentType = $uploadResult['type'];
        }
    }

    if (!empty($errors)) {
        return ['ok' => false, 'errors' => $errors];
    }

    // Проверяем — есть ли уже анкета для этой записи?
    $existing = getFormByAppointment($db, $appointmentId);

    if ($existing) {
        // UPDATE — но если новый файл загружен, удаляем старый
        if ($attachmentFile && !empty($existing['attachment_file'])) {
            deleteAppointmentAttachment($existing['attachment_file']);
        }
        // Если файл не перезагружался — оставляем старый
        if (!$attachmentFile) {
            $attachmentFile = $existing['attachment_file'];
            $attachmentType = $existing['attachment_type'];
        }

        $stmt = $db->prepare(
            "UPDATE appointment_forms
             SET complaint=?, symptoms=?, symptoms_other=?, pet_age_value=?, pet_age_unit=?,
                 urgency=?, has_tests=?, attachment_file=?, attachment_type=?
             WHERE id = ?"
        );
        $stmt->bind_param(
            'sssisisssi',
            $complaint, $symptomsCsv, $symptomsOther, $petAgeValue, $petAgeUnit,
            $urgency, $hasTests, $attachmentFile, $attachmentType, $existing['id']
        );
        $stmt->execute();
        $formId = (int)$existing['id'];
        $stmt->close();
    } else {
        $stmt = $db->prepare(
            "INSERT INTO appointment_forms
             (appointment_id, complaint, symptoms, symptoms_other,
              pet_age_value, pet_age_unit, urgency, has_tests,
              attachment_file, attachment_type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'isssisssss',
            $appointmentId, $complaint, $symptomsCsv, $symptomsOther,
            $petAgeValue, $petAgeUnit, $urgency, $hasTests,
            $attachmentFile, $attachmentType
        );
        $stmt->execute();
        $formId = (int)$stmt->insert_id;
        $stmt->close();
    }

    return ['ok' => true, 'form_id' => $formId];
}

/**
 * Загрузка файла-аттача анкеты.
 * Разрешено: JPG, PNG, WebP, PDF; до 5 МБ.
 */
function uploadAppointmentAttachment(array $file, int $appointmentId): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Некорректные данные файла'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $codes = [
            UPLOAD_ERR_INI_SIZE   => 'Файл превышает upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE  => 'Файл слишком большой',
            UPLOAD_ERR_PARTIAL    => 'Файл загружен частично',
            UPLOAD_ERR_NO_TMP_DIR => 'Нет временной папки',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл',
        ];
        return ['ok' => false, 'error' => $codes[$file['error']] ?? 'Ошибка загрузки'];
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Файл больше 5 МБ'];
    }

    // Определяем реальный тип
    $mime = '';
    $type = '';
    $ext  = '';

    $info = @getimagesize($file['tmp_name']);
    if ($info !== false) {
        $imageMap = [
            IMAGETYPE_JPEG => ['mime' => 'image/jpeg', 'ext' => 'jpg'],
            IMAGETYPE_PNG  => ['mime' => 'image/png',  'ext' => 'png'],
            IMAGETYPE_WEBP => ['mime' => 'image/webp', 'ext' => 'webp'],
        ];
        if (isset($imageMap[$info[2]])) {
            $mime = $imageMap[$info[2]]['mime'];
            $ext  = $imageMap[$info[2]]['ext'];
            $type = 'image';
        }
    }

    // Проверка PDF (если getimagesize не определил)
    if ($type === '' && function_exists('mime_content_type')) {
        $detectedMime = @mime_content_type($file['tmp_name']);
        if ($detectedMime === 'application/pdf') {
            // Доп. проверка магических байтов %PDF-
            $fh = fopen($file['tmp_name'], 'rb');
            if ($fh) {
                $magic = fread($fh, 5);
                fclose($fh);
                if ($magic === '%PDF-') {
                    $mime = 'application/pdf';
                    $ext  = 'pdf';
                    $type = 'pdf';
                }
            }
        }
    }

    if ($type === '') {
        return ['ok' => false, 'error' => 'Разрешены только JPG, PNG, WebP и PDF'];
    }

    $filename = 'attach_' . $appointmentId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads/appointment_attachments/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $targetPath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['ok' => false, 'error' => 'Не удалось сохранить файл'];
    }

    return ['ok' => true, 'filename' => $filename, 'type' => $type];
}

function deleteAppointmentAttachment(?string $filename): bool
{
    if (!$filename) return false;
    if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || strpos($filename, '..') !== false) {
        return false;
    }
    $path = __DIR__ . '/../uploads/appointment_attachments/' . $filename;
    if (file_exists($path)) {
        return @unlink($path);
    }
    return false;
}

/** Бейдж срочности — HTML */
function urgencyBadge(string $urgency): string
{
    $list = appointmentUrgencyList();
    if (!isset($list[$urgency])) $urgency = 'planned';
    $u = $list[$urgency];

    $colors = [
        'planned'   => ['bg' => '#e8f5e9', 'fg' => '#1b5e20'],
        'week'      => ['bg' => '#fff8e1', 'fg' => '#8a6500'],
        'urgent'    => ['bg' => '#fff3e0', 'fg' => '#bf360c'],
        'emergency' => ['bg' => '#ffebee', 'fg' => '#b71c1c'],
    ];
    $c = $colors[$urgency];

    return '<span class="urgency-badge" style="background:' . $c['bg']
         . ';color:' . $c['fg'] . ';display:inline-flex;align-items:center;gap:6px;'
         . 'padding:4px 10px;border-radius:14px;font-size:.78rem;font-weight:600;'
         . 'white-space:nowrap;">'
         . $u['icon'] . ' ' . htmlspecialchars($u['label']) . '</span>';
}
