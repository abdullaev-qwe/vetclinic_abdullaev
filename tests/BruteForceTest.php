<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Тесты защиты от brute-force по IP
 * и работы с токенами восстановления пароля.
 */
final class BruteForceTest extends TestCase
{
    private string $testIp = '192.168.1.100';

    protected function setUp(): void
    {
        $_SESSION = [];
    }

    #[Test]
    public function fresh_ip_is_not_limited(): void
    {
        $this->assertFalse(isIpRateLimited($this->testIp));
    }

    #[Test]
    public function ip_is_not_limited_after_few_attempts(): void
    {
        for ($i = 0; $i < 10; $i++) {
            registerIpAttempt($this->testIp);
        }
        $this->assertFalse(isIpRateLimited($this->testIp));
    }

    #[Test]
    public function ip_is_limited_after_20_attempts(): void
    {
        for ($i = 0; $i < 20; $i++) {
            registerIpAttempt($this->testIp);
        }
        $this->assertTrue(isIpRateLimited($this->testIp), 'После 20 попыток IP должен быть заблокирован');
    }

    #[Test]
    public function ip_remains_limited_after_more_attempts(): void
    {
        for ($i = 0; $i < 25; $i++) {
            registerIpAttempt($this->testIp);
        }
        $this->assertTrue(isIpRateLimited($this->testIp));
    }

    #[Test]
    public function reset_clears_ip_counter(): void
    {
        for ($i = 0; $i < 20; $i++) {
            registerIpAttempt($this->testIp);
        }
        $this->assertTrue(isIpRateLimited($this->testIp));

        resetIpAttempts($this->testIp);
        $this->assertFalse(isIpRateLimited($this->testIp));
    }

    #[Test]
    public function different_ips_have_separate_counters(): void
    {
        $ip1 = '192.168.1.100';
        $ip2 = '10.0.0.50';

        // Блокируем IP1
        for ($i = 0; $i < 20; $i++) {
            registerIpAttempt($ip1);
        }

        $this->assertTrue(isIpRateLimited($ip1),  'IP1 должен быть заблокирован');
        $this->assertFalse(isIpRateLimited($ip2), 'IP2 не должен быть затронут');
    }

    #[Test]
    public function expired_counter_is_cleared(): void
    {
        // Имитируем истёкший счётчик: ставим reset_at в прошлое
        $key = 'ip_attempts_' . md5($this->testIp);
        $_SESSION[$key] = [
            'count'    => 25,
            'reset_at' => time() - 100, // уже истёк
            'ip'       => $this->testIp,
        ];

        // Должен быть не заблокирован, т.к. время истекло
        $this->assertFalse(isIpRateLimited($this->testIp));
        // И счётчик должен быть очищен
        $this->assertArrayNotHasKey($key, $_SESSION);
    }

    // ── Тесты токенов восстановления пароля ──

    #[Test]
    public function reset_token_is_hashed_with_sha256(): void
    {
        $raw  = 'random_token_value_12345';
        $hash = hashResetToken($raw);
        // SHA-256 даёт 64 hex-символа
        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $hash);
        // Хеш должен совпадать с ожидаемым
        $this->assertSame(hash('sha256', $raw), $hash);
    }

    #[Test]
    public function same_token_produces_same_hash(): void
    {
        $raw = 'test_token';
        $this->assertSame(hashResetToken($raw), hashResetToken($raw));
    }

    #[Test]
    public function different_tokens_produce_different_hashes(): void
    {
        $this->assertNotSame(hashResetToken('token1'), hashResetToken('token2'));
    }

    #[Test]
    public function token_not_expired_in_future(): void
    {
        $future = date('Y-m-d H:i:s', time() + 900); // +15 минут
        $this->assertFalse(isTokenExpired($future));
    }

    #[Test]
    public function token_is_expired_in_past(): void
    {
        $past = date('Y-m-d H:i:s', time() - 60);
        $this->assertTrue(isTokenExpired($past));
    }
}
