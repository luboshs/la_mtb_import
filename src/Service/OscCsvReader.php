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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Reads and validates osCommerce CSV export files.
 *
 * Supports three file types:
 *  - products CSV  : columns from osCommerce products + descriptions tables combined
 *  - specials CSV  : osCommerce specials table
 *  - categories CSV: osCommerce categories + descriptions tables combined
 *
 * Filtering applied at read time:
 *  - products  : only rows with products_status = "1"
 *  - specials  : only rows with status = "1"
 */
class MtbOscCsvReader
{
    /** Columns that MUST be present in the products CSV */
    const REQUIRED_PRODUCT_COLUMNS = [
        'products_id',
        'products_name',
        'products_price',
        'products_status',
    ];

    /** Columns that MUST be present in the specials CSV */
    const REQUIRED_SPECIALS_COLUMNS = [
        'specials_id',
        'products_id',
        'specials_new_products_price',
        'status',
    ];

    /** Columns that MUST be present in the categories CSV */
    const REQUIRED_CATEGORIES_COLUMNS = [
        'categories_id',
        'categories_name',
    ];

    /** Optional product columns; missing ones default to empty string */
    const OPTIONAL_PRODUCT_COLUMNS = [
        'products_model',
        'manufacturers_name',
        'products_description',
        'products_tax_class_id',
        'products_image',
        'products_date_available',
        'categories_ids',
        'availability',
        'is_new',
        'is_optimum',
        'subimage1',
        'subimage2',
        'subimage3',
        'subimage4',
        'subimage5',
        'subimage6',
    ];

    /** Optional specials columns */
    const OPTIONAL_SPECIALS_COLUMNS = [
        'specials_date_added',
        'expires_date',
    ];

    /** Optional categories columns */
    const OPTIONAL_CATEGORIES_COLUMNS = [
        'parent_id',
    ];

    /**
     * Read a products CSV file and return only active (products_status=1) rows.
     *
     * @param string $filePath Absolute path to the CSV file.
     * @return array Array of row arrays keyed by column name.
     * @throws InvalidArgumentException If the file cannot be read or required columns are missing.
     */
    public function readProducts($filePath)
    {
        return $this->readCsv(
            $filePath,
            self::REQUIRED_PRODUCT_COLUMNS,
            self::OPTIONAL_PRODUCT_COLUMNS,
            function (array $row) {
                return isset($row['products_status']) && (string) $row['products_status'] === '1';
            }
        );
    }

    /**
     * Read a specials CSV file and return only active (status=1) rows.
     *
     * @param string $filePath Absolute path to the CSV file.
     * @return array Array of row arrays keyed by column name.
     * @throws InvalidArgumentException If the file cannot be read or required columns are missing.
     */
    public function readSpecials($filePath)
    {
        return $this->readCsv(
            $filePath,
            self::REQUIRED_SPECIALS_COLUMNS,
            self::OPTIONAL_SPECIALS_COLUMNS,
            function (array $row) {
                return isset($row['status']) && (string) $row['status'] === '1';
            }
        );
    }

    /**
     * Read a categories CSV file and return all rows.
     *
     * @param string $filePath Absolute path to the CSV file.
     * @return array Array of row arrays keyed by column name.
     * @throws InvalidArgumentException If the file cannot be read or required columns are missing.
     */
    public function readCategories($filePath)
    {
        return $this->readCsv(
            $filePath,
            self::REQUIRED_CATEGORIES_COLUMNS,
            self::OPTIONAL_CATEGORIES_COLUMNS
        );
    }

    /**
     * Open a CSV file and read all rows matching the optional filter callback.
     *
     * @param string        $filePath        Path to CSV file.
     * @param array         $requiredColumns Columns that must exist in the header row.
     * @param array         $optionalColumns Columns that are added with empty-string default if absent.
     * @param callable|null $rowFilter       Callable that receives a row array and returns true to keep it.
     * @return array
     * @throws InvalidArgumentException On file/header errors.
     */
    protected function readCsv($filePath, array $requiredColumns, array $optionalColumns = [], $rowFilter = null)
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException('CSV file not found or not readable: ' . $filePath);
        }

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('Cannot open CSV file: ' . $filePath);
        }

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);

        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle, 0, ',');

        if ($headers === false || empty($headers)) {
            fclose($handle);
            throw new InvalidArgumentException('CSV file is empty or has no header row: ' . $filePath);
        }

        // Trim header names
        $headers = array_map('trim', $headers);

        $this->validateHeaders($headers, $requiredColumns, $filePath);

        $allKnown = array_merge($requiredColumns, $optionalColumns);
        $rows = [];

        while (($line = fgetcsv($handle, 0, ',')) !== false) {
            if ($line === [null]) {
                continue;
            }

            $row = $this->buildRow($headers, $line, $allKnown);

            if ($rowFilter === null || $rowFilter($row)) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Build a row array from a raw fgetcsv line, mapping by header name.
     *
     * Only the known columns (required + optional) are returned.
     * Missing optional columns are set to empty string.
     *
     * @param array $headers     Header names from the first CSV row.
     * @param array $line        Current data line from fgetcsv.
     * @param array $knownColumns All columns to include in the output row.
     * @return array
     */
    protected function buildRow(array $headers, array $line, array $knownColumns)
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            $mapped[$header] = isset($line[$index]) ? (string) $line[$index] : '';
        }

        $row = [];

        foreach ($knownColumns as $col) {
            $row[$col] = array_key_exists($col, $mapped) ? $mapped[$col] : '';
        }

        return $row;
    }

    /**
     * Throw if any required column is missing from the header row.
     *
     * @param array  $headers         Actual headers found in the CSV.
     * @param array  $requiredColumns Column names that must be present.
     * @param string $filePath        Used in the error message.
     * @throws InvalidArgumentException
     */
    protected function validateHeaders(array $headers, array $requiredColumns, $filePath = '')
    {
        $missing = array_diff($requiredColumns, $headers);

        if (!empty($missing)) {
            throw new InvalidArgumentException(
                'CSV file ' . $filePath . ' is missing required columns: ' . implode(', ', $missing)
            );
        }
    }
}
