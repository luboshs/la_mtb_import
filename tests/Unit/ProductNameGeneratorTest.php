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

require_once __DIR__ . '/../../src/Service/ProductNameGenerator.php';

class ProductNameGeneratorTest extends TestCase
{
    private MtbProductNameGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new MtbProductNameGenerator();
    }

    public function testGenerateReturnsEmptyForEmptyInput(): void
    {
        $this->assertSame('', $this->generator->generate(''));
        $this->assertSame('', $this->generator->generate('   '));
    }

    public function testGenerateAppliesCsdSubstitution(): void
    {
        $result = $this->generator->generate('T466 2364 CSD');
        $this->assertStringContainsString('ČSD', $result);
    }

    public function testGenerateAppliesCdSubstitution(): void
    {
        $result = $this->generator->generate('E499 0001 CD');
        $this->assertStringContainsString('ČD', $result);
    }

    public function testGenerateDetectsDieselType(): void
    {
        $result = $this->generator->generate('T466 2364 CSD');
        $this->assertStringContainsString('Dieselová', $result);
    }

    public function testGenerateDetectsElectricType(): void
    {
        $result = $this->generator->generate('E499 0001 CSD');
        $this->assertStringContainsString('Elektrická', $result);
    }

    public function testGenerateDetectsMotorCarType(): void
    {
        $result = $this->generator->generate('M131 0527 CSD');
        $this->assertStringContainsString('Motorový', $result);
    }

    public function testGenerateNormalizesSeriesNumber(): void
    {
        $result = $this->generator->generate('T466 2364 CSD');
        $this->assertStringContainsString('T466.2364', $result);
    }

    public function testGenerateAppliesPlusSubstitution(): void
    {
        $result = $this->generator->generate('T466 + tender');
        $this->assertStringContainsString(' s ', $result);
    }

    public function testGenerateReturnsStringType(): void
    {
        $result = $this->generator->generate('T466 CSD');
        $this->assertIsString($result);
    }

    public function testGenerateHandlesUnknownPrefix(): void
    {
        $result = $this->generator->generate('Unknown Product Name');
        $this->assertNotEmpty($result);
    }

    public function testGenerateFullExample(): void
    {
        $result = $this->generator->generate('T466 2364 CSD');
        $this->assertStringContainsString('T466.2364', $result);
        $this->assertStringContainsString('ČSD', $result);
    }
}
