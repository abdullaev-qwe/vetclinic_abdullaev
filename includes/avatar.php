<?php
/**
 * avatar.php — функции работы с аватарками пользователя
 */

/**
 * Цветовые палитры по специализации/имени (HSL → стабильный цвет от хеша)
 */
function avatarColorFromName(string $name): array
{
    $hash = crc32($name);
    $hue  = $hash % 360;
    return [
        'bg'   => "hsl($hue, 35%, 92%)",  // светлый фон
        'fg'   => "hsl($hue, 60%, 32%)",  // тёмный текст
    ];
}

/**
 * Получить инициалы из имени (макс. 2 буквы)
 */
function avatarInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach ($parts as $p) {
        if ($p === '') continue;
        $initials .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($initials) >= 2) break;
    }
    return $initials ?: '?';
}

/**
 * Получить URL аватарки или null если не загружена
 */
function avatarUrl(?string $filename): ?string
{
    if (!$filename) return null;
    $path = __DIR__ . '/../uploads/avatars/' . $filename;
    if (!file_exists($path)) return null;
    return '/vetclinic/uploads/avatars/' . $filename;
}

/**
 * Отрисовать аватар пользователя:
 * - если есть файл — <img>
 * - иначе — div с инициалами на цветном фоне
 */
function renderAvatar(string $name, ?string $filename, int $size = 80): string
{
    $url = avatarUrl($filename);

    if ($url) {
        return '<img src="' . htmlspecialchars($url) . '" alt="" '
             . 'style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;'
             . 'object-fit:cover;display:block;" '
             . 'loading="lazy" decoding="async">';
    }

    // Заглушка с инициалами
    $colors = avatarColorFromName($name);
    $initials = avatarInitials($name);
    $fontSize = (int)($size / 2.6);

    return '<div style="width:' . $size . 'px;height:' . $size . 'px;border-radius:50%;'
         . 'background:' . $colors['bg'] . ';color:' . $colors['fg'] . ';'
         . 'display:flex;align-items:center;justify-content:center;'
         . 'font-family:var(--font-display, Georgia, serif);font-weight:700;'
         . 'font-size:' . $fontSize . 'px;line-height:1;flex-shrink:0;">'
         . htmlspecialchars($initials) . '</div>';
}

/**
 * Безопасная загрузка аватара
 *
 * @return array{ok: bool, filename?: string, error?: string}
 */
function uploadAvatar(array $file, int $userId): array
{
    // Проверка ошибки загрузки
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Некорректные данные файла'];
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Файл не выбран'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Ошибка загрузки файла'];
    }

    // Проверка размера: 2 МБ
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Файл слишком большой (макс. 2 МБ)'];
    }

    // Проверка реального типа через getimagesize
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'error' => 'Это не изображение'];
    }

    $allowedTypes = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    if (!isset($allowedTypes[$info[2]])) {
        return ['ok' => false, 'error' => 'Разрешены только JPG, PNG и WebP'];
    }

    // Проверка размеров
    [$width, $height] = $info;
    if ($width < 64 || $height < 64) {
        return ['ok' => false, 'error' => 'Слишком маленькое изображение (мин. 64×64)'];
    }
    if ($width > 4000 || $height > 4000) {
        return ['ok' => false, 'error' => 'Слишком большое изображение (макс. 4000×4000)'];
    }

    // Генерируем уникальное имя
    $ext = $allowedTypes[$info[2]];
    $filename = 'user_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

    $uploadDir = __DIR__ . '/../uploads/avatars/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $targetPath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['ok' => false, 'error' => 'Не удалось сохранить файл'];
    }

    return ['ok' => true, 'filename' => $filename];
}

/**
 * Удалить файл аватара (если существует)
 */
function deleteAvatarFile(?string $filename): bool
{
    if (!$filename) return false;
    // Защита от path traversal
    if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false || strpos($filename, '..') !== false) {
        return false;
    }
    $path = __DIR__ . '/../uploads/avatars/' . $filename;
    if (file_exists($path)) {
        return @unlink($path);
    }
    return false;
}
