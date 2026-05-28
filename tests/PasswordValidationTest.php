<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Тесты валидации пароля и email.
 */
final class PasswordValidationTest extends TestCase
{
    // ── Пароль: корректные варианты ──

    #[Test]
    public function valid_password_passes_all_checks(): void
    {
        $errors = validatePassword('Password1!');
        $this->assertEmpty($errors, 'Корректный пароль не должен давать ошибок');
    }

    #[Test]
    public function valid_complex_password(): void
    {
        $errors = validatePassword('MyStr0ng@Pass2024');
        $this->assertEmpty($errors);
    }

    // ── Пароль: длина ──

    #[Test]
    public function short_password_is_rejected(): void
    {
        $errors = validatePassword('Abc1!');
        $this->assertContains('Минимум 8 символов.', $errors);
    }

    #[Test]
    public function empty_password_is_rejected(): void
    {
        $errors = validatePassword('');
        $this->assertContains('Минимум 8 символов.', $errors);
        $this->assertContains('Минимум 1 заглавная буква.', $errors);
        $this->assertContains('Минимум 1 цифра.', $errors);
        $this->assertContains('Минимум 1 спецсимвол.', $errors);
    }

    // ── Пароль: заглавная буква ──

    #[Test]
    public function password_without_uppercase_is_rejected(): void
    {
        $errors = validatePassword('password1!');
        $this->assertContains('Минимум 1 заглавная буква.', $errors);
    }

    // ── Пароль: цифра ──

    #[Test]
    public function password_without_digit_is_rejected(): void
    {
        $errors = validatePassword('Password!');
        $this->assertContains('Минимум 1 цифра.', $errors);
    }

    // ── Пароль: спецсимвол ──

    #[Test]
    public function password_without_special_char_is_rejected(): void
    {
        $errors = validatePassword('Password1');
        $this->assertContains('Минимум 1 спецсимвол.', $errors);
    }

    // ── Параметризованный тест всех спецсимволов ──

    #[Test]
    #[DataProvider('specialCharactersProvider')]
    public function password_accepts_various_special_chars(string $specialChar): void
    {
        $password = 'Password1' . $specialChar;
        $errors = validatePassword($password);
        $this->assertEmpty($errors, "Символ '$specialChar' должен приниматься");
    }

    public static function specialCharactersProvider(): array
    {
        return [
            'восклицание'    => ['!'],
            'собачка'        => ['@'],
            'решётка'        => ['#'],
            'доллар'         => ['$'],
            'процент'        => ['%'],
            'крышка'         => ['^'],
            'амперсанд'      => ['&'],
            'звёздочка'      => ['*'],
            'скобки круглые' => ['('],
            'дефис'          => ['-'],
            'подчёркивание'  => ['_'],
        ];
    }

    // ── Email: корректные ──

    #[Test]
    #[DataProvider('validEmailProvider')]
    public function valid_emails_are_accepted(string $email): void
    {
        $this->assertTrue(validateEmail($email));
    }

    public static function validEmailProvider(): array
    {
        return [
            'простой'     => ['test@example.com'],
            'с точкой'    => ['first.last@example.com'],
            'с цифрами'   => ['user123@example.com'],
            'поддомен'    => ['test@mail.example.com'],
            'с плюсом'    => ['test+tag@example.com'],
        ];
    }

    // ── Email: некорректные ──

    #[Test]
    #[DataProvider('invalidEmailProvider')]
    public function invalid_emails_are_rejected(string $email): void
    {
        $this->assertFalse(validateEmail($email));
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'пустой'         => [''],
            'без @'          => ['testexample.com'],
            'без домена'     => ['test@'],
            'без имени'      => ['@example.com'],
            'без TLD'        => ['test@example'],
            'два @'          => ['te@st@example.com'],
            'пробелы'        => ['test @example.com'],
        ];
    }

    // ── Телефон ──

    #[Test]
    public function valid_phones_are_accepted(): void
    {
        $this->assertTrue(validatePhone('+79001234567'));
        $this->assertTrue(validatePhone('+7 900 123-45-67'));
        $this->assertTrue(validatePhone('89001234567'));
    }

    #[Test]
    public function invalid_phones_are_rejected(): void
    {
        $this->assertFalse(validatePhone('abc'));
        $this->assertFalse(validatePhone('123'));
        $this->assertFalse(validatePhone(''));
    }

    // ── XSS защита (экранирование) ──

    #[Test]
    public function e_function_escapes_html_tags(): void
    {
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
    }

    #[Test]
    public function e_function_escapes_quotes(): void
    {
        $this->assertSame('&quot;test&quot;', e('"test"'));
        $this->assertSame('&#039;test&#039;', e("'test'"));
    }

    #[Test]
    public function e_function_escapes_ampersand(): void
    {
        $this->assertSame('Tom &amp; Jerry', e('Tom & Jerry'));
    }
}
