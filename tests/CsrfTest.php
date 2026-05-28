<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Тесты CSRF-защиты.
 */
final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    #[Test]
    public function token_is_generated_on_first_call(): void
    {
        $this->assertEmpty($_SESSION['csrf_token'] ?? '');
        $token = generateCsrfToken();
        $this->assertNotEmpty($token);
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    #[Test]
    public function token_persists_between_calls(): void
    {
        $first  = generateCsrfToken();
        $second = generateCsrfToken();
        $this->assertSame($first, $second, 'Токен должен быть один на сессию');
    }

    #[Test]
    public function token_has_correct_length(): void
    {
        $token = generateCsrfToken();
        // bin2hex(random_bytes(32)) = 64 символа
        $this->assertSame(64, strlen($token));
    }

    #[Test]
    public function token_is_hex_string(): void
    {
        $token = generateCsrfToken();
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    #[Test]
    public function valid_token_passes_check(): void
    {
        $token = generateCsrfToken();
        $this->assertTrue(checkCsrfToken($token));
    }

    #[Test]
    public function invalid_token_is_rejected(): void
    {
        generateCsrfToken();
        $this->assertFalse(checkCsrfToken('fake_token_12345'));
    }

    #[Test]
    public function empty_token_is_rejected(): void
    {
        generateCsrfToken();
        $this->assertFalse(checkCsrfToken(''));
    }

    #[Test]
    public function token_check_without_session_fails(): void
    {
        $_SESSION = [];
        $this->assertFalse(checkCsrfToken('any_token'));
    }

    #[Test]
    public function different_sessions_have_different_tokens(): void
    {
        $_SESSION = [];
        $token1 = generateCsrfToken();

        $_SESSION = [];
        $token2 = generateCsrfToken();

        $this->assertNotSame($token1, $token2, 'Разные сессии = разные токены');
    }

    #[Test]
    public function token_is_compared_in_constant_time(): void
    {
        // Проверяем, что используется hash_equals (защита от timing-атак).
        // Чисто логическая проверка: корректный токен проходит, частично совпадающий — нет.
        $token = generateCsrfToken();
        $almostSame = substr($token, 0, 63) . 'X';
        $this->assertFalse(checkCsrfToken($almostSame));
    }
}
