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
 * Imports a prepared MTB import record as a PrestaShop product.
 */
class MtbProductImportService
{
    /**
     * Import a prepared record into PrestaShop as a new product.
     *
     * Admin must have selected a category and optionally a manufacturer.
     * The product is created as inactive so the admin can review before publishing.
     *
     * @param int $importId The ID from mtb_import_product table.
     * @param int $idCategory The PrestaShop category ID.
     * @param int $idManufacturer The PrestaShop manufacturer ID (0 = none).
     * @return int The new product ID, or 0 on failure.
     * @throws Exception On critical errors.
     */
    public function import($importId, $idCategory, $idManufacturer)
    {
        $importRecord = $this->getImportRecord((int) $importId);

        if (empty($importRecord)) {
            throw new Exception('Import record not found: ' . (int) $importId);
        }

        if ((int) ($importRecord['status'] ?? 0) === 1) {
            throw new Exception('Product is already imported.');
        }

        $languages = Language::getLanguages();
        $defaultLangId = (int) Configuration::get('PS_LANG_DEFAULT');

        $displayName = !empty($importRecord['admin_name'])
            ? $importRecord['admin_name']
            : (!empty($importRecord['generated_name'])
                ? $importRecord['generated_name']
                : $importRecord['supplier_raw_name']);

        $product = new Product();
        $product->active = 0;
        $product->id_category_default = (int) $idCategory;
        $product->id_manufacturer = (int) $idManufacturer;
        $product->reference = pSQL((string) ($importRecord['supplier_reference'] ?? ''));
        $product->ean13 = pSQL((string) ($importRecord['ean_normalized'] ?? ''));
        $product->price = (float) ($importRecord['dealer_price'] ?? 0);
        $product->show_price = 1;
        $product->available_for_order = 1;
        $product->visibility = 'catalog';

        foreach ($languages as $lang) {
            $product->name[$lang['id_lang']] = pSQL(
                $this->getNameForLang((int) $importId, (int) $lang['id_lang'], $displayName)
            );
            $product->description[$lang['id_lang']] = pSQL(
                $this->getDescriptionForLang((int) $importId, (int) $lang['id_lang'])
            );
            $product->description_short[$lang['id_lang']] = '';
            $product->link_rewrite[$lang['id_lang']] = Tools::link_rewrite(
                $product->name[$defaultLangId] ?? $displayName
            );
            $product->meta_title[$lang['id_lang']] = $product->name[$lang['id_lang']];
        }

        if (!$product->add()) {
            throw new Exception('Failed to create product in PrestaShop.');
        }

        $product->addToCategories([$idCategory]);

        $this->markImported((int) $importId, (int) $product->id);

        MtbModelImporter::log(
            'Product imported: ' . $displayName,
            'info',
            ['import_id' => (int) $importId, 'product_id' => (int) $product->id]
        );

        return (int) $product->id;
    }

    /**
     * Retrieve a single import record by ID.
     *
     * @param int $importId
     * @return array|false
     */
    protected function getImportRecord($importId)
    {
        return Db::getInstance()->getRow(
            "SELECT * FROM `" . _DB_PREFIX_ . "mtb_import_product`
            WHERE `id` = " . (int) $importId
        );
    }

    /**
     * Get the product name for a language, falling back to the default.
     *
     * @param int $importId
     * @param int $idLang
     * @param string $defaultName
     * @return string
     */
    protected function getNameForLang($importId, $idLang, $defaultName)
    {
        $row = Db::getInstance()->getRow(
            "SELECT `name`, `status`
            FROM `" . _DB_PREFIX_ . "mtb_import_product_lang`
            WHERE `id_product_import` = " . (int) $importId . "
                AND `id_lang` = " . (int) $idLang
        );

        if ($row && !empty($row['name'])) {
            return (string) $row['name'];
        }

        return (string) $defaultName;
    }

    /**
     * Get the product description for a language, falling back to empty.
     *
     * @param int $importId
     * @param int $idLang
     * @return string
     */
    protected function getDescriptionForLang($importId, $idLang)
    {
        $row = Db::getInstance()->getRow(
            "SELECT `description`
            FROM `" . _DB_PREFIX_ . "mtb_import_product_lang`
            WHERE `id_product_import` = " . (int) $importId . "
                AND `id_lang` = " . (int) $idLang
        );

        if ($row && !empty($row['description'])) {
            return (string) $row['description'];
        }

        return '';
    }

    /**
     * Update the import record to reflect a successful import.
     *
     * @param int $importId
     * @param int $productId
     * @return bool
     */
    protected function markImported($importId, $productId)
    {
        return Db::getInstance()->update(
            MtbModelImporter::TABLE_PRODUCT,
            [
                'id_product' => (int) $productId,
                'status' => MtbModelImporter::STATUS_IMPORTED,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            '`id` = ' . (int) $importId
        );
    }
}
