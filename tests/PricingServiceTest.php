<?php

declare(strict_types=1);

use App\Services\PricingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PricingServiceTest extends TestCase
{
    public static function pricingCases(): array
    {
        return [
            '1 cartela' => [1, 2.00],
            '3 cartelas' => [3, 5.00],
            '11 cartelas' => [11, 19.00],
            '15 cartelas' => [15, 25.00],
            '23 cartelas' => [23, 39.00],
        ];
    }

    #[DataProvider('pricingCases')]
    public function testOfficialPricingFormula(int $quantity, float $expected): void
    {
        $rule = [
            'single_price' => 2.00,
            'bundle_quantity' => 3,
            'bundle_price' => 5.00,
        ];

        $result = PricingService::calculate($quantity, $rule);

        self::assertSame($expected, $result['amount']);
    }
}
