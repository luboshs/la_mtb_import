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
 * Maps osCommerce category IDs to PrestaShop category IDs.
 *
 * The mapping is stored in mtb_osc_category_map:
 *  - ps_id_category IS NOT NULL → use that PS category
 *  - ignore_binding = 1          → skip this osC category (product is not placed in it)
 *  - ps_id_category IS NULL AND ignore_binding = 0 → unmapped; fallback category applies
 *
 * Multiple osC category IDs per product are resolved independently.
 * A product can end up in several PS categories.
 *
 * Fallback behaviour:
 *  When no mapped PS category is found for a product, the configured
 *  MTB_OSC_FALLBACK_CATEGORY is returned.  The fallback category is meant to
 *  be hidden (active=0 in PS) while products inside it remain searchable
 *  (visibility = 'search' is set by OscProductImporter in this case).
 */
class MtbOscCategoryMapper
{
    /**
     * Resolve a list of osC category IDs to PS category IDs.
     *
     * @param array $oscCategoryIds Array of int osCommerce category IDs.
     * @return int[] Deduplicated list of PS category IDs (empty = use fallback).
     */
    public function getPsCategories(array $oscCategoryIds)
    {
        $psIds = [];

        foreach ($oscCategoryIds as $oscId) {
            $oscId = (int) $oscId;

            if ($oscId <= 0) {
                continue;
            }

            $mapping = $this->getMapping($oscId);

            if ($mapping === null) {
                // Unknown category – treat as unmapped (fallback applies)
                continue;
            }

            if ((int) $mapping['ignore_binding'] === 1) {
                // Category is explicitly ignored
                continue;
            }

            $psId = (int) $mapping['ps_id_category'];

            if ($psId > 0) {
                $psIds[] = $psId;
            }
        }

        return array_values(array_unique($psIds));
    }

    /**
     * Return the configured fallback PS category ID, or 0 if not configured.
     *
     * @return int
     */
    public function getFallbackCategoryId()
    {
        return (int) Configuration::get(MtbModelImporter::CONFIG_OSC_FALLBACK_CATEGORY);
    }

    /**
     * Return true when the given osC category has its ignore_binding flag set.
     *
     * @param int $oscCategoryId
     * @return bool
     */
    public function isIgnored($oscCategoryId)
    {
        $mapping = $this->getMapping((int) $oscCategoryId);

        if ($mapping === null) {
            return false;
        }

        return (int) $mapping['ignore_binding'] === 1;
    }

    /**
     * Fetch the category map row for the given osC category ID.
     *
     * @param int $oscCategoryId
     * @return array|null
     */
    protected function getMapping($oscCategoryId)
    {
        $row = Db::getInstance()->getRow(
            "SELECT `ps_id_category`, `ignore_binding`
            FROM `" . _DB_PREFIX_ . "mtb_osc_category_map`
            WHERE `osc_categories_id` = " . (int) $oscCategoryId
        );

        return $row ?: null;
    }
}
