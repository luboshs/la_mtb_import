<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    luboshs
 * @copyright since 2026 luboshs
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Helper/EanNormalizer.php';

class EanNormalizerTest extends TestCase
{
    private MtbEanNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new MtbEanNormalizer();
    }

    /**
     * @dataProvider eanProvider
     */
    public function testNormalize(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function eanProvider(): array
    {
        return [
            'removes leading zeros' => ['0761742652369', '761742652369'],
            'strips non-digits' => ['07-617-426-52369', '761742652369'],
            'multiple leading zeros' => ['000123456', '123456'],
            'empty string' => ['', ''],
            'all zeros' => ['000', ''],
            'spaces in EAN' => ['761 742 652 369', '761742652369'],
            'letters mixed' => ['EAN761742', '761742'],
            'no leading zeros' => ['761742652369', '761742652369'],
            'single digit' => ['5', '5'],
            'only non-digits' => ['EAN:', ''],
        ];
    }

    public function testNormalizeReturnsString(): void
    {
        $result = $this->normalizer->normalize('0123');
        $this->assertIsString($result);
    }

    public function testNormalizeDoesNotModifyNormalizedEan(): void
    {
        $ean = '761742652369';
        $this->assertSame($ean, $this->normalizer->normalize($ean));
    }
}
