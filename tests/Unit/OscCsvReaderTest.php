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

require_once __DIR__ . '/../../src/Service/OscCsvReader.php';

class OscCsvReaderTest extends TestCase
{
    private MtbOscCsvReader $reader;

    /** @var string Temp directory for test CSV files */
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->reader = new MtbOscCsvReader();
        $this->tmpDir = sys_get_temp_dir() . '/osc_csv_reader_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        array_map('unlink', glob($this->tmpDir . '/*.csv'));
        @rmdir($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // Products CSV
    // -------------------------------------------------------------------------

    public function testReadProductsReturnsOnlyActiveRows(): void
    {
        $csv = $this->makeCsv(
            ['products_id', 'products_name', 'products_price', 'products_status'],
            [
                ['1', 'Active Product', '10.00', '1'],
                ['2', 'Inactive Product', '20.00', '0'],
                ['3', 'Another Active', '30.00', '1'],
            ]
        );

        $rows = $this->reader->readProducts($csv);

        $this->assertCount(2, $rows);
        $this->assertSame('1', $rows[0]['products_id']);
        $this->assertSame('3', $rows[1]['products_id']);
    }

    public function testReadProductsIncludesOptionalColumnsWithDefault(): void
    {
        $csv = $this->makeCsv(
            ['products_id', 'products_name', 'products_price', 'products_status'],
            [
                ['5', 'Product Without Optional', '15.00', '1'],
            ]
        );

        $rows = $this->reader->readProducts($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]['manufacturers_name']);
        $this->assertSame('', $rows[0]['subimage1']);
        $this->assertSame('', $rows[0]['subimage6']);
        $this->assertSame('', $rows[0]['categories_ids']);
    }

    public function testReadProductsPreservesOptionalColumnValues(): void
    {
        $csv = $this->makeCsv(
            ['products_id', 'products_name', 'products_price', 'products_status',
             'manufacturers_name', 'categories_ids', 'subimage1'],
            [
                ['7', 'Test Product', '9.99', '1', 'Roco', '12|34', 'img1.jpg'],
            ]
        );

        $rows = $this->reader->readProducts($csv);

        $this->assertSame('Roco', $rows[0]['manufacturers_name']);
        $this->assertSame('12|34', $rows[0]['categories_ids']);
        $this->assertSame('img1.jpg', $rows[0]['subimage1']);
    }

    public function testReadProductsThrowsOnMissingRequiredColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/missing required columns/');

        $csv = $this->makeCsv(
            ['products_id', 'products_name'], // missing products_price, products_status
            [['1', 'Foo']]
        );

        $this->reader->readProducts($csv);
    }

    public function testReadProductsThrowsOnMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->reader->readProducts('/nonexistent/path/to/file.csv');
    }

    public function testReadProductsStripsUtf8Bom(): void
    {
        $path = $this->tmpDir . '/bom_products.csv';
        $content = "\xEF\xBB\xBF" . "products_id,products_name,products_price,products_status\n"
            . "1,BOM Product,5.00,1\n";
        file_put_contents($path, $content);

        $rows = $this->reader->readProducts($path);

        $this->assertCount(1, $rows);
        $this->assertSame('1', $rows[0]['products_id']);
    }

    public function testReadProductsEmptyFileThrows(): void
    {
        $path = $this->tmpDir . '/empty.csv';
        file_put_contents($path, '');

        $this->expectException(InvalidArgumentException::class);

        $this->reader->readProducts($path);
    }

    public function testReadProductsSkipsBlankLines(): void
    {
        $csv = $this->makeCsv(
            ['products_id', 'products_name', 'products_price', 'products_status'],
            [
                ['1', 'Product A', '10.00', '1'],
                [],   // blank line
                ['2', 'Product B', '20.00', '1'],
            ]
        );

        $rows = $this->reader->readProducts($csv);

        $this->assertCount(2, $rows);
    }

    // -------------------------------------------------------------------------
    // Specials CSV
    // -------------------------------------------------------------------------

    public function testReadSpecialsReturnsOnlyActiveRows(): void
    {
        $csv = $this->makeCsv(
            ['specials_id', 'products_id', 'specials_new_products_price', 'status'],
            [
                ['1', '10', '5.00', '1'],
                ['2', '11', '8.00', '0'],
            ]
        );

        $rows = $this->reader->readSpecials($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('1', $rows[0]['specials_id']);
    }

    public function testReadSpecialsThrowsOnMissingRequiredColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $csv = $this->makeCsv(
            ['specials_id', 'products_id'],
            [['1', '10']]
        );

        $this->reader->readSpecials($csv);
    }

    // -------------------------------------------------------------------------
    // Categories CSV
    // -------------------------------------------------------------------------

    public function testReadCategoriesReturnsAllRows(): void
    {
        $csv = $this->makeCsv(
            ['categories_id', 'categories_name'],
            [
                ['1', 'Locomotives'],
                ['2', 'Wagons'],
            ]
        );

        $rows = $this->reader->readCategories($csv);

        $this->assertCount(2, $rows);
    }

    public function testReadCategoriesIncludesParentIdDefault(): void
    {
        $csv = $this->makeCsv(
            ['categories_id', 'categories_name'],
            [['3', 'Category Without Parent']]
        );

        $rows = $this->reader->readCategories($csv);

        $this->assertArrayHasKey('parent_id', $rows[0]);
        $this->assertSame('', $rows[0]['parent_id']);
    }

    public function testReadCategoriesWithParentId(): void
    {
        $csv = $this->makeCsv(
            ['categories_id', 'categories_name', 'parent_id'],
            [['5', 'Child Category', '1']]
        );

        $rows = $this->reader->readCategories($csv);

        $this->assertSame('1', $rows[0]['parent_id']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Write a CSV to a temp file and return its path.
     *
     * @param array $headers
     * @param array $dataRows Array of arrays (each inner array is one row).
     * @return string File path.
     */
    private function makeCsv(array $headers, array $dataRows): string
    {
        $path = $this->tmpDir . '/test_' . uniqid() . '.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);

        foreach ($dataRows as $row) {
            if (empty($row)) {
                fwrite($handle, "\n");
            } else {
                fputcsv($handle, $row);
            }
        }

        fclose($handle);

        return $path;
    }
}
