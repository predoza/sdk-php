<?php

declare(strict_types=1);

namespace Predoza\Tests;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Predoza\HostSignIn\InvalidSiteKey;
use Predoza\HostSignIn\SiteKey;
use Predoza\Testing\TestKeys;

final class SiteKeyTest extends TestCase
{
    #[DataProvider('damagedValues')]
    public function testADamagedValueIsRefusedWithAHint(string $value): void
    {
        $this->expectException(InvalidSiteKey::class);
        $this->expectExceptionMessageMatches('/Host sign-in page/');

        new SiteKey($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function damagedValues(): iterable
    {
        $parts = explode('.', TestKeys::create()->envValue);

        yield 'empty' => [''];
        yield 'a v1 secret' => [str_repeat('a', 64)];
        yield 'wrong prefix' => ['pzpk.' . implode('.', array_slice($parts, 1))];
        yield 'seed cut short' => [implode('.', [...array_slice($parts, 0, 3), substr($parts[3], 0, 20)])];
        yield 'Predoza key cut short' => [implode('.', [$parts[0], $parts[1], substr($parts[2], 0, 20), $parts[3]])];
    }

    public function testThePrivateHalfNeverPrints(): void
    {
        $envValue = TestKeys::create()->envValue;
        $seed = explode('.', $envValue)[3];
        $siteKey = new SiteKey($envValue);

        self::assertStringNotContainsString($seed, print_r($siteKey, true));
        self::assertStringNotContainsString($seed, var_export($siteKey->__debugInfo(), true));

        $this->expectException(LogicException::class);
        serialize($siteKey);
    }
}
