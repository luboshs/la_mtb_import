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

require_once __DIR__ . '/../../src/Service/DealerPasteParser.php';

class DealerPasteParserTest extends TestCase
{
    private MtbDealerPasteParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MtbDealerPasteParser();
    }

    public function testParseReturnsEmptyForEmptyInput(): void
    {
        $this->assertSame([], $this->parser->parse(''));
        $this->assertSame([], $this->parser->parse('   '));
    }

    public function testParseExtractsEan(): void
    {
        $text = "Dieselová lokomotíva T478\nEAN: 1234567890123";
        $result = $this->parser->parse($text);
        $this->assertNotEmpty($result);
        $this->assertSame('1234567890123', $result[0]['ean']);
    }

    public function testParseExtractsPrice(): void
    {
        $text = "Dieselová lokomotíva T478\n1250 €";
        $result = $this->parser->parse($text);
        $this->assertNotEmpty($result);
        $this->assertSame(1250.0, $result[0]['price']);
    }

    public function testParseDetectsSuspendedStatus(): void
    {
        $text = "Lokomotíva E499\nObjednávky pozastaveny";
        $result = $this->parser->parse($text);
        $this->assertNotEmpty($result);
        $this->assertSame(MtbDealerPasteParser::ORDER_STATUS_SUSPENDED, $result[0]['order_status']);
    }

    public function testParseDetectsUnavailableStatus(): void
    {
        $text = "Lokomotíva E499\nnelze objednat";
        $result = $this->parser->parse($text);
        $this->assertNotEmpty($result);
        $this->assertSame(MtbDealerPasteParser::ORDER_STATUS_UNAVAILABLE, $result[0]['order_status']);
    }

    public function testParseDetectsBearingsFlag(): void
    {
        $text = "Lokomotíva E499\ns kuličkovými ložisky";
        $result = $this->parser->parse($text);
        $this->assertNotEmpty($result);
        $this->assertTrue($result[0]['has_bearings']);
    }

    public function testParseDetectsDccFlag(): void
    {
        $text = "Lokomotíva E499\ns DCC dekodér integrovaný";
        $result = $this->parser->parse($text);
        $this->assertNotEmpty($result);
        $this->assertTrue($result[0]['has_integrated_dcc']);
    }

    public function testParseMultipleProducts(): void
    {
        $text = "Lokomotíva T478\nEAN: 1111111111111\n1000 €\n\nMotorový vozeň M131\nEAN: 2222222222222\n500 €";
        $result = $this->parser->parse($text);
        $this->assertCount(2, $result);
        $this->assertSame('1111111111111', $result[0]['ean']);
        $this->assertSame('2222222222222', $result[1]['ean']);
    }

    public function testParseDefaultOrderStatusIsAvailable(): void
    {
        $text = "Lokomotíva T478\nEAN: 1111111111111";
        $result = $this->parser->parse($text);
        $this->assertNotEmpty($result);
        $this->assertSame(MtbDealerPasteParser::ORDER_STATUS_AVAILABLE, $result[0]['order_status']);
    }

    public function testParseStripsTags(): void
    {
        $text = "<b>Lokomotíva T478</b>\nEAN: <span>1111111111111</span>";
        $result = $this->parser->parse($text);
        $this->assertNotEmpty($result);
        $this->assertSame('1111111111111', $result[0]['ean']);
    }

    public function testParseResultHasExpectedKeys(): void
    {
        $text = "Lokomotíva T478\nEAN: 1234567890";
        $result = $this->parser->parse($text);
        $this->assertNotEmpty($result);

        $expectedKeys = [
            'supplier_raw_name', 'scale', 'ean', 'price',
            'category', 'note', 'order_status', 'has_bearings', 'has_integrated_dcc', 'reference',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result[0], "Missing key: {$key}");
        }
    }
}
