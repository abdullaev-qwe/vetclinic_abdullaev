<?php
/**
 * cache.php — Файловый кеш для VetCare
 *
 * Использование:
 *   $doctors = cacheGet('doctors_active', 300, function() use ($db) {
 *       return $db->query("SELECT * FROM doctors WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);
 *   });
 *
 *   cacheClear('doctors_active');         // удалить конкретный ключ
 *   cacheClearGroup('doctors');           // удалить все ключи с префиксом 'doctors_'
 *   cacheFlush();                         // очистить весь кеш
 */

class Cache
{
    private static string $dir;
    private static bool $initialized = false;

    private static function init(): void
    {
        if (self::$initialized) return;
        self::$dir = __DIR__ . '/../cache';
        if (!is_dir(self::$dir)) {
            @mkdir(self::$dir, 0755, true);
        }
        // Защита от листинга директории
        $htaccess = self::$dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }
        self::$initialized = true;
    }

    private static function path(string $key): string
    {
        self::init();
        // Безопасное имя файла
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return self::$dir . '/' . $safe . '.cache';
    }

    /**
     * Получить значение из кеша. Если нет или истекло — вычислить через callback и сохранить.
     *
     * @param string   $key      ключ кеша
     * @param int      $ttl      время жизни в секундах
     * @param callable $callback функция вычисления значения
     * @return mixed
     */
    public static function get(string $key, int $ttl, callable $callback)
    {
        $file = self::path($key);

        // Проверяем существование и срок жизни
        if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
            $data = @file_get_contents($file);
            if ($data !== false) {
                $unpacked = @unserialize($data);
                if ($unpacked !== false || $data === serialize(false)) {
                    return $unpacked;
                }
            }
        }

        // Вычисляем заново
        $value = $callback();
        @file_put_contents($file, serialize($value), LOCK_EX);
        return $value;
    }

    /**
     * Удалить конкретный ключ.
     */
    public static function clear(string $key): void
    {
        $file = self::path($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Удалить все ключи начинающиеся с префикса.
     */
    public static function clearGroup(string $prefix): void
    {
        self::init();
        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prefix);
        $files = glob(self::$dir . '/' . $safePrefix . '*.cache');
        if ($files) {
            foreach ($files as $f) @unlink($f);
        }
    }

    /**
     * Очистить весь кеш.
     */
    public static function flush(): void
    {
        self::init();
        $files = glob(self::$dir . '/*.cache');
        if ($files) {
            foreach ($files as $f) @unlink($f);
        }
    }

    /**
     * Получить статистику кеша (для админки).
     */
    public static function stats(): array
    {
        self::init();
        $files = glob(self::$dir . '/*.cache');
        $totalSize = 0;
        $oldest    = null;
        $newest    = null;

        if ($files) {
            foreach ($files as $f) {
                $size = filesize($f);
                $time = filemtime($f);
                $totalSize += $size;
                if ($oldest === null || $time < $oldest) $oldest = $time;
                if ($newest === null || $time > $newest) $newest = $time;
            }
        }

        return [
            'count'      => count($files ?: []),
            'total_size' => $totalSize,
            'oldest'     => $oldest,
            'newest'     => $newest,
        ];
    }
}

// ── Удобные хелпер-функции ──

function cacheGet(string $key, int $ttl, callable $callback) {
    return Cache::get($key, $ttl, $callback);
}

function cacheClear(string $key): void {
    Cache::clear($key);
}

function cacheClearGroup(string $prefix): void {
    Cache::clearGroup($prefix);
}

function cacheFlush(): void {
    Cache::flush();
}
