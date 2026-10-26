<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public static function scaledProvider(): array
    {
        return [
            ['44.99', 2, 4499],
            ['99', 2, 9900],
            ['1530.0000', 4, 15300000],
            ['1530.5', 4, 15305000],
            ['0.125', 2, 13],
            ['-1.5', 2, -150],
        ];
    }

    #[DataProvider('scaledProvider')]
    public function test_to_scaled(string $value, int $scale, int $expected): void
    {
        $this->assertSame($expected, Money::toScaled($value, $scale));
    }

    public function test_to_scaled_rejects_garbage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::toScaled('1,5', 2);
    }

    public function test_convert_rounds_to_cents(): void
    {
        $this->assertSame(45747000, Money::convert(29900, 15300000));
        $this->assertSame(6883470, Money::convert(4499, 15300000));
        // 0,01 USD × 1530,5 = 15,305 → 15,31
        $this->assertSame(1531, Money::convert(1, 15305000));
    }

    public function test_format_like_the_prototype(): void
    {
        $this->assertSame('$457.470', Money::ars(45747000));
        $this->assertSame('$68.834,70', Money::ars(6883470));
        $this->assertSame('USD 44,99', Money::usd(4499));
        $this->assertSame('USD 299', Money::usd(29900));
    }
}
