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
 * Imports staged osCommerce products into PrestaShop.
 *
 * Design principles enforced by this service:
 *
 *  - No duplicate imports: before creating a PS product the service checks
 *    mtb_osc_product_map. If osc_products_id is already mapped, the record
 *    is skipped.
 *
 *  - Old IDs are kept in mapping only: osc_products_id is never used as
 *    id_product in PrestaShop; PS auto-generates its own ID.
 *
 *  - Multiple PS categories: the product is added to every PS category that
 *    the osC category IDs resolve to.
 *
 *  - Fallback category: when no PS category can be resolved the product is
 *    placed in the configured fallback category with visibility='search'.
 *    The fallback category itself must be set to active=0 by the admin.
 *
 *  - Prices without VAT: products_price from osCommerce is the net price and
 *    is stored directly as product->price (PS also stores net price).
 *
 *  - Tax rule group: resolved from MTB_OSC_TAX_MAP_23 / MTB_OSC_TAX_MAP_5
 *    config (comma-separated osC tax_class_id lists) which map to the PS tax
 *    rule groups named "23 %" and "5 %" respectively.
 *
 *  - Manufacturer: matched by name via MtbOscManufacturerMapper; created if
 *    not found.
 *
 *  - Images: downloaded from the old URL (MTB_OSC_BASE_IMAGE_URL + filename)
 *    via MtbImageImportService. Supports products_image + subimage1–6.
 *
 *  - Stock: not imported (quantity=0, stock management off).
 *
 *  - Redirects: a product redirect record is written to mtb_osc_redirect
 *    after successful import.
 *
 *  - Batch processing: importBatch() processes N pending records so the
 *    admin can trigger sequential batch runs without PHP timeouts.
 */
class MtbOscProductImporter
{
    /** @var MtbOscCategoryMapper */
    private $categoryMapper;

    /** @var MtbOscManufacturerMapper */
    private $manufacturerMapper;

    /** @var MtbImageImportService */
    private $imageService;

    /** @var MtbOscRedirectManager */
    private $redirectManager;

    /**
     * @param MtbOscCategoryMapper     $categoryMapper
     * @param MtbOscManufacturerMapper $manufacturerMapper
     * @param MtbImageImportService    $imageService
     * @param MtbOscRedirectManager    $redirectManager
     */
    public function __construct(
        MtbOscCategoryMapper $categoryMapper,
        MtbOscManufacturerMapper $manufacturerMapper,
        MtbImageImportService $imageService,
        MtbOscRedirectManager $redirectManager
    ) {
        $this->categoryMapper = $categoryMapper;
        $this->manufacturerMapper = $manufacturerMapper;
        $this->imageService = $imageService;
        $this->redirectManager = $redirectManager;
    }

    /**
     * Process a batch of pending staged products.
     *
     * @param int $batchSize Number of products to process in this run.
     * @return array ['imported' => int, 'skipped' => int, 'errors' => int]
     */
    public function importBatch($batchSize = 50)
    {
        $batchSize = max(1, (int) $batchSize);
        $prefix = _DB_PREFIX_;

        $rows = Db::getInstance()->executeS(
            "SELECT *
            FROM `{$prefix}mtb_osc_product`
            WHERE `import_status` = 'pending'
            ORDER BY `id` ASC
            LIMIT " . $batchSize
        );

        if (!is_array($rows) || empty($rows)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($rows as $row) {
            try {
                $result = $this->importOne($row);

                if ($result > 0) {
                    ++$imported;
                } else {
                    ++$skipped;
                }
            } catch (Exception $e) {
                ++$errors;
                MtbModelImporter::log(
                    'OSC product import error (osc_id=' . (int) $row['osc_products_id'] . '): ' . $e->getMessage(),
                    'error'
                );
                $this->markStagingStatus((int) $row['id'], 'skipped');
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Import a single staged product row into PrestaShop.
     *
     * @param array $row Row from mtb_osc_product.
     * @return int New PS product ID, or 0 if skipped.
     * @throws Exception On fatal errors.
     */
    public function importOne(array $row)
    {
        $oscId = (int) $row['osc_products_id'];

        // Skip if already imported (deduplication)
        if ($this->isAlreadyImported($oscId)) {
            $this->markStagingStatus((int) $row['id'], 'skipped');

            return 0;
        }

        // Resolve PS categories
        $oscCategoryIds = $this->parsePipeIds((string) $row['categories_ids']);
        $psCategories = $this->categoryMapper->getPsCategories($oscCategoryIds);

        $useFallback = empty($psCategories);
        $fallbackId = $this->categoryMapper->getFallbackCategoryId();

        if ($useFallback && $fallbackId <= 0) {
            throw new Exception('No PS category mapped and no fallback category configured for osc_id=' . $oscId);
        }

        if ($useFallback) {
            $psCategories = [$fallbackId];
        }

        $defaultCategory = $psCategories[0];

        // Manufacturer
        $idManufacturer = $this->manufacturerMapper->getOrCreate((string) $row['manufacturers_name']);

        // Tax rule group
        $idTaxRulesGroup = $this->resolveTaxRulesGroup((int) $row['products_tax_class_id']);

        // Build PS product
        $product = new Product();
        $product->active = 0; // Admin reviews before activating
        $product->id_category_default = $defaultCategory;
        $product->id_manufacturer = $idManufacturer > 0 ? $idManufacturer : 0;
        $product->reference = pSQL((string) $row['products_model']);
        $product->price = (float) $row['products_price'];
        $product->id_tax_rules_group = $idTaxRulesGroup;
        $product->show_price = 1;
        $product->available_for_order = 0; // stock not imported
        $product->quantity = 0;
        // Visibility: search-only when placed in fallback, catalog otherwise
        $product->visibility = $useFallback ? 'search' : 'catalog';

        $defaultLangId = (int) Configuration::get('PS_LANG_DEFAULT');
        $languages = Language::getLanguages();

        foreach ($languages as $lang) {
            $product->name[$lang['id_lang']] = pSQL((string) $row['products_name']);
            $product->description[$lang['id_lang']] = $row['products_description'];
            $product->description_short[$lang['id_lang']] = '';
            $product->link_rewrite[$lang['id_lang']] = Tools::link_rewrite(
                (string) ($product->name[$defaultLangId] ?? $row['products_name'])
            );
            $product->meta_title[$lang['id_lang']] = $product->name[$lang['id_lang']];
        }

        if (!$product->add()) {
            throw new Exception('Failed to create PS product for osc_id=' . $oscId);
        }

        $psProductId = (int) $product->id;

        // Add to all resolved PS categories
        $product->addToCategories($psCategories);

        // Import images
        $this->importImages($psProductId, $row);

        // Write product_map entry (old ID stored in mapping only)
        $this->saveProductMap($oscId, $psProductId);

        // Mark staging record as imported
        $this->markStagingStatus((int) $row['id'], 'imported');

        // Write redirect record
        $oscUrl = $this->buildOscProductUrl($oscId);
        $psUrl = $this->buildPsProductUrl($psProductId, $product);
        $this->redirectManager->addProductRedirect($oscId, $oscUrl, $psUrl);

        MtbModelImporter::log(
            'OSC product imported: ' . $row['products_name'],
            'info',
            ['osc_id' => $oscId, 'ps_id' => $psProductId]
        );

        return $psProductId;
    }

    /**
     * Return true if the osc product was already imported (exists in product_map).
     *
     * @param int $oscId
     * @return bool
     */
    protected function isAlreadyImported($oscId)
    {
        $row = Db::getInstance()->getRow(
            "SELECT `id`
            FROM `" . _DB_PREFIX_ . "mtb_osc_product_map`
            WHERE `osc_products_id` = " . (int) $oscId
        );

        return (bool) $row;
    }

    /**
     * Resolve the PS tax rules group ID from an osCommerce tax_class_id.
     *
     * Config MTB_OSC_TAX_MAP_23 / MTB_OSC_TAX_MAP_5 hold comma-separated
     * lists of osC tax_class_id values.
     *
     * @param int $oscTaxClassId
     * @return int PS id_tax_rules_group, or 0 if not mappable.
     */
    protected function resolveTaxRulesGroup($oscTaxClassId)
    {
        if ($oscTaxClassId <= 0) {
            return 0;
        }

        $map23 = $this->parseIntList((string) Configuration::get(MtbModelImporter::CONFIG_OSC_TAX_MAP_23));
        $map5 = $this->parseIntList((string) Configuration::get(MtbModelImporter::CONFIG_OSC_TAX_MAP_5));

        if (in_array($oscTaxClassId, $map23, true)) {
            return $this->findTaxRulesGroupByRate(23.0);
        }

        if (in_array($oscTaxClassId, $map5, true)) {
            return $this->findTaxRulesGroupByRate(5.0);
        }

        return 0;
    }

    /**
     * Find the first PS tax rules group that contains a tax rule with the given rate.
     *
     * @param float $rate
     * @return int id_tax_rules_group, or 0 if not found.
     */
    protected function findTaxRulesGroupByRate($rate)
    {
        $row = Db::getInstance()->getRow(
            "SELECT trg.`id_tax_rules_group`
            FROM `" . _DB_PREFIX_ . "tax_rules_group` trg
            INNER JOIN `" . _DB_PREFIX_ . "tax_rule` tr
                ON tr.`id_tax_rules_group` = trg.`id_tax_rules_group`
            INNER JOIN `" . _DB_PREFIX_ . "tax` t
                ON t.`id_tax` = tr.`id_tax`
            WHERE trg.`active` = 1
                AND t.`rate` = " . (float) $rate . "
            LIMIT 1"
        );

        return $row ? (int) $row['id_tax_rules_group'] : 0;
    }

    /**
     * Download and attach product images.
     *
     * Downloads: products_image, subimage1 – subimage6.
     * Each filename is prefixed with the configured base image URL.
     *
     * @param int   $psProductId
     * @param array $row Staging row.
     * @return void
     */
    protected function importImages($psProductId, array $row)
    {
        $baseUrl = rtrim((string) Configuration::get(MtbModelImporter::CONFIG_OSC_BASE_IMAGE_URL), '/');

        if ($baseUrl === '') {
            return;
        }

        $imageColumns = [
            'products_image',
            'subimage1', 'subimage2', 'subimage3',
            'subimage4', 'subimage5', 'subimage6',
        ];

        $urls = [];

        foreach ($imageColumns as $col) {
            $filename = trim((string) ($row[$col] ?? ''));

            if ($filename !== '') {
                $urls[] = $baseUrl . '/' . ltrim($filename, '/');
            }
        }

        if (!empty($urls)) {
            $this->imageService->downloadAndImport($psProductId, $urls);
        }
    }

    /**
     * Save the osc_products_id → ps_id_product mapping.
     *
     * @param int $oscId
     * @param int $psId
     * @return void
     */
    protected function saveProductMap($oscId, $psId)
    {
        Db::getInstance()->insert(
            MtbModelImporter::TABLE_OSC_PRODUCT_MAP,
            [
                'osc_products_id' => (int) $oscId,
                'ps_id_product' => (int) $psId,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Update the import_status column on the staging record.
     *
     * @param int    $stagingId
     * @param string $status 'imported' | 'skipped'
     * @return void
     */
    protected function markStagingStatus($stagingId, $status)
    {
        Db::getInstance()->update(
            MtbModelImporter::TABLE_OSC_PRODUCT,
            [
                'import_status' => pSQL($status),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            '`id` = ' . (int) $stagingId
        );
    }

    /**
     * Build the canonical osCommerce product page URL for redirect recording.
     *
     * @param int $oscId
     * @return string
     */
    protected function buildOscProductUrl($oscId)
    {
        return 'product_info.php?products_id=' . (int) $oscId;
    }

    /**
     * Build the PS canonical product URL.
     *
     * @param int     $psProductId
     * @param Product $product
     * @return string
     */
    protected function buildPsProductUrl($psProductId, Product $product)
    {
        $defaultLangId = (int) Configuration::get('PS_LANG_DEFAULT');

        try {
            return (string) $product->getLink();
        } catch (Exception $e) {
            return 'index.php?id_product=' . $psProductId . '&controller=product';
        }
    }

    /**
     * Parse a pipe-separated list of integer IDs.
     *
     * @param string $value
     * @return int[]
     */
    protected function parsePipeIds($value)
    {
        if (trim($value) === '') {
            return [];
        }

        $ids = [];

        foreach (explode('|', $value) as $part) {
            $id = (int) trim($part);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Parse a comma-separated list of integers.
     *
     * @param string $value
     * @return int[]
     */
    protected function parseIntList($value)
    {
        if (trim($value) === '') {
            return [];
        }

        $ids = [];

        foreach (explode(',', $value) as $part) {
            $id = (int) trim($part);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
