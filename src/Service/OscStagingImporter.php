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
 * Loads osCommerce CSV export files into PrestaShop staging tables.
 *
 * On each import run:
 *  - Products: INSERT IGNORE – existing rows (same osc_products_id) are not overwritten.
 *  - Specials: same INSERT IGNORE strategy.
 *  - Categories: upserts osc_category_name but preserves any existing PS mapping.
 *
 * After loading products, all unique manufacturer names are registered in
 * mtb_osc_manufacturer_map and all unique category IDs are registered in
 * mtb_osc_category_map so that admins can complete the mapping later.
 */
class MtbOscStagingImporter
{
    /** @var MtbOscCsvReader */
    private $csvReader;

    /**
     * @param MtbOscCsvReader $csvReader
     */
    public function __construct(MtbOscCsvReader $csvReader)
    {
        $this->csvReader = $csvReader;
    }

    /**
     * Import a products CSV into the mtb_osc_product staging table.
     *
     * Only active products (products_status=1) are loaded.
     *
     * @param string $filePath Absolute path to the products CSV.
     * @return array ['inserted' => int, 'skipped' => int]
     * @throws InvalidArgumentException On CSV errors.
     */
    public function importProducts($filePath)
    {
        $rows = $this->csvReader->readProducts($filePath);
        $inserted = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');

        $manufacturerNames = [];
        $categoryIds = [];

        foreach ($rows as $row) {
            $oscId = (int) $row['products_id'];

            if ($oscId <= 0) {
                ++$skipped;
                continue;
            }

            $data = [
                'osc_products_id' => $oscId,
                'products_model' => pSQL((string) $row['products_model']),
                'manufacturers_name' => pSQL((string) $row['manufacturers_name']),
                'products_name' => pSQL((string) $row['products_name']),
                'products_description' => pSQL((string) $row['products_description'], true),
                'products_price' => (float) str_replace(',', '.', $row['products_price']),
                'products_tax_class_id' => $row['products_tax_class_id'] !== ''
                    ? (int) $row['products_tax_class_id']
                    : null,
                'products_image' => pSQL((string) $row['products_image']),
                'products_date_available' => $this->sanitizeDate($row['products_date_available']),
                'categories_ids' => pSQL((string) $row['categories_ids']),
                'availability' => pSQL((string) $row['availability']),
                'is_new' => (int) (bool) $row['is_new'],
                'is_optimum' => (int) (bool) $row['is_optimum'],
                'subimage1' => pSQL((string) $row['subimage1']),
                'subimage2' => pSQL((string) $row['subimage2']),
                'subimage3' => pSQL((string) $row['subimage3']),
                'subimage4' => pSQL((string) $row['subimage4']),
                'subimage5' => pSQL((string) $row['subimage5']),
                'subimage6' => pSQL((string) $row['subimage6']),
                'import_status' => 'pending',
                'created_at' => pSQL($now),
                'updated_at' => pSQL($now),
            ];

            $result = Db::getInstance()->insert(
                MtbModelImporter::TABLE_OSC_PRODUCT,
                $data,
                false,
                true,  // null_values
                Db::INSERT_IGNORE
            );

            if ($result) {
                ++$inserted;
            } else {
                ++$skipped;
            }

            // Collect manufacturer names for later mapping
            $name = trim((string) $row['manufacturers_name']);

            if ($name !== '') {
                $manufacturerNames[$name] = true;
            }

            // Collect all osc category IDs from pipe-separated list
            $catIds = $this->parsePipeSeparatedIds((string) $row['categories_ids']);

            foreach ($catIds as $catId) {
                $categoryIds[$catId] = true;
            }
        }

        // Register new manufacturer names (without mapping yet)
        $this->registerManufacturerNames(array_keys($manufacturerNames));

        // Register discovered category IDs (without PS mapping yet)
        $this->registerCategoryIds(array_keys($categoryIds));

        MtbModelImporter::log(
            'OSC products CSV staged',
            'info',
            ['inserted' => $inserted, 'skipped' => $skipped, 'file' => basename($filePath)]
        );

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    /**
     * Import a specials CSV into the mtb_osc_specials staging table.
     *
     * Only active specials (status=1) are loaded.
     *
     * @param string $filePath Absolute path to the specials CSV.
     * @return array ['inserted' => int, 'skipped' => int]
     */
    public function importSpecials($filePath)
    {
        $rows = $this->csvReader->readSpecials($filePath);
        $inserted = 0;
        $skipped = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $oscSpecialsId = (int) $row['specials_id'];
            $oscProductsId = (int) $row['products_id'];

            if ($oscSpecialsId <= 0 || $oscProductsId <= 0) {
                ++$skipped;
                continue;
            }

            $data = [
                'osc_specials_id' => $oscSpecialsId,
                'osc_products_id' => $oscProductsId,
                'specials_new_products_price' => (float) str_replace(',', '.', $row['specials_new_products_price']),
                'specials_date_added' => $this->sanitizeDate($row['specials_date_added'] ?? ''),
                'expires_date' => $this->sanitizeDate($row['expires_date'] ?? ''),
                'import_status' => 'pending',
                'created_at' => pSQL($now),
            ];

            $result = Db::getInstance()->insert(
                MtbModelImporter::TABLE_OSC_SPECIALS,
                $data,
                false,
                true,
                Db::INSERT_IGNORE
            );

            if ($result) {
                ++$inserted;
            } else {
                ++$skipped;
            }
        }

        MtbModelImporter::log(
            'OSC specials CSV staged',
            'info',
            ['inserted' => $inserted, 'skipped' => $skipped, 'file' => basename($filePath)]
        );

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    /**
     * Import a categories CSV into the mtb_osc_category_map table.
     *
     * If a category already exists in the map, only its name is updated;
     * any existing PS category mapping is preserved.
     *
     * @param string $filePath Absolute path to the categories CSV.
     * @return array ['inserted' => int, 'updated' => int, 'skipped' => int]
     */
    public function importCategories($filePath)
    {
        $rows = $this->csvReader->readCategories($filePath);
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $prefix = _DB_PREFIX_;

        foreach ($rows as $row) {
            $oscId = (int) $row['categories_id'];

            if ($oscId <= 0) {
                ++$skipped;
                continue;
            }

            $name = pSQL((string) $row['categories_name']);

            $existing = Db::getInstance()->getRow(
                "SELECT `id` FROM `{$prefix}mtb_osc_category_map`
                WHERE `osc_categories_id` = " . $oscId
            );

            if ($existing) {
                Db::getInstance()->execute(
                    "UPDATE `{$prefix}mtb_osc_category_map`
                    SET `osc_category_name` = '" . $name . "'
                    WHERE `osc_categories_id` = " . $oscId
                );
                ++$updated;
            } else {
                Db::getInstance()->insert(
                    'mtb_osc_category_map',
                    [
                        'osc_categories_id' => $oscId,
                        'osc_category_name' => $name,
                        'ps_id_category' => null,
                        'ignore_binding' => 0,
                    ],
                    false,
                    true
                );
                ++$inserted;
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * Register manufacturer names in mtb_osc_manufacturer_map if not already present.
     *
     * @param array $names
     * @return void
     */
    protected function registerManufacturerNames(array $names)
    {
        foreach ($names as $name) {
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            Db::getInstance()->insert(
                'mtb_osc_manufacturer_map',
                [
                    'osc_manufacturers_name' => pSQL($name),
                    'ps_id_manufacturer' => null,
                ],
                false,
                true,
                Db::INSERT_IGNORE
            );
        }
    }

    /**
     * Register category IDs in mtb_osc_category_map if not already present.
     *
     * @param array $ids Array of int category IDs.
     * @return void
     */
    protected function registerCategoryIds(array $ids)
    {
        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id <= 0) {
                continue;
            }

            Db::getInstance()->insert(
                'mtb_osc_category_map',
                [
                    'osc_categories_id' => $id,
                    'osc_category_name' => '',
                    'ps_id_category' => null,
                    'ignore_binding' => 0,
                ],
                false,
                true,
                Db::INSERT_IGNORE
            );
        }
    }

    /**
     * Parse a pipe-separated string of integers.
     *
     * @param string $value
     * @return int[]
     */
    protected function parsePipeSeparatedIds($value)
    {
        if (trim($value) === '') {
            return [];
        }

        $parts = explode('|', $value);
        $ids = [];

        foreach ($parts as $part) {
            $id = (int) trim($part);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Return a SQL-safe date string or NULL string for DB insertion.
     *
     * @param string $value Raw date string from CSV.
     * @return string|null
     */
    protected function sanitizeDate($value)
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '0000-00-00') {
            return null;
        }

        // Accept YYYY-MM-DD or D.M.YYYY formats
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return pSQL($value);
        }

        $ts = strtotime($value);

        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }
}
