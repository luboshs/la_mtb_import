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
 * Manages redirect records for migrated osCommerce URLs.
 *
 * Redirects are stored in mtb_osc_redirect.  The table can be queried by an
 * .htaccess rule or a PrestaShop front controller to issue 301 redirects from
 * old osCommerce URLs (product_info.php, index.php?cPath=…) to their new PS
 * equivalents.
 *
 * Each record is unique per (type, osc_id) combination.  A second call for the
 * same pair updates the ps_url in place.
 */
class MtbOscRedirectManager
{
    /**
     * Upsert a product redirect record.
     *
     * @param int    $oscProductId osCommerce products_id.
     * @param string $oscUrl       Old osCommerce URL (relative or absolute).
     * @param string $psUrl        New PrestaShop URL (relative or absolute).
     * @return bool
     */
    public function addProductRedirect($oscProductId, $oscUrl, $psUrl)
    {
        return $this->upsert('product', (int) $oscProductId, $oscUrl, $psUrl);
    }

    /**
     * Upsert a category redirect record.
     *
     * @param int    $oscCategoryId osCommerce categories_id.
     * @param string $oscUrl        Old osCommerce category URL.
     * @param string $psUrl         New PrestaShop category URL.
     * @return bool
     */
    public function addCategoryRedirect($oscCategoryId, $oscUrl, $psUrl)
    {
        return $this->upsert('category', (int) $oscCategoryId, $oscUrl, $psUrl);
    }

    /**
     * Return all redirect records.
     *
     * @return array
     */
    public function getAll()
    {
        $rows = Db::getInstance()->executeS(
            "SELECT *
            FROM `" . _DB_PREFIX_ . "mtb_osc_redirect`
            ORDER BY `type` ASC, `osc_id` ASC"
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Return redirect records filtered by type.
     *
     * @param string $type 'product' or 'category'.
     * @return array
     */
    public function getByType($type)
    {
        $type = in_array($type, ['product', 'category'], true) ? $type : 'product';

        $rows = Db::getInstance()->executeS(
            "SELECT *
            FROM `" . _DB_PREFIX_ . "mtb_osc_redirect`
            WHERE `type` = '" . pSQL($type) . "'
            ORDER BY `osc_id` ASC"
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Look up the PS URL for an old osCommerce product URL by osc_products_id.
     *
     * @param int $oscProductId
     * @return string|null
     */
    public function findProductRedirect($oscProductId)
    {
        $row = Db::getInstance()->getRow(
            "SELECT `ps_url`
            FROM `" . _DB_PREFIX_ . "mtb_osc_redirect`
            WHERE `type` = 'product'
                AND `osc_id` = " . (int) $oscProductId
        );

        return $row ? (string) $row['ps_url'] : null;
    }

    /**
     * Look up the PS URL for an old osCommerce category URL by osc_categories_id.
     *
     * @param int $oscCategoryId
     * @return string|null
     */
    public function findCategoryRedirect($oscCategoryId)
    {
        $row = Db::getInstance()->getRow(
            "SELECT `ps_url`
            FROM `" . _DB_PREFIX_ . "mtb_osc_redirect`
            WHERE `type` = 'category'
                AND `osc_id` = " . (int) $oscCategoryId
        );

        return $row ? (string) $row['ps_url'] : null;
    }

    /**
     * Insert or update a redirect record.
     *
     * @param string $type 'product' | 'category'
     * @param int    $oscId
     * @param string $oscUrl
     * @param string $psUrl
     * @return bool
     */
    protected function upsert($type, $oscId, $oscUrl, $psUrl)
    {
        $prefix = _DB_PREFIX_;

        $existing = Db::getInstance()->getRow(
            "SELECT `id`
            FROM `{$prefix}mtb_osc_redirect`
            WHERE `type` = '" . pSQL($type) . "'
                AND `osc_id` = " . (int) $oscId
        );

        if ($existing) {
            return Db::getInstance()->update(
                'mtb_osc_redirect',
                [
                    'osc_url' => pSQL($oscUrl),
                    'ps_url' => pSQL($psUrl),
                ],
                "`type` = '" . pSQL($type) . "' AND `osc_id` = " . (int) $oscId
            );
        }

        return Db::getInstance()->insert(
            'mtb_osc_redirect',
            [
                'type' => pSQL($type),
                'osc_id' => (int) $oscId,
                'osc_url' => pSQL($oscUrl),
                'ps_url' => pSQL($psUrl),
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
    }
}
