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
 * Imports osCommerce specials as PrestaShop SpecificPrice records.
 *
 * Only staged specials whose corresponding osCommerce product has already
 * been imported (exists in mtb_osc_product_map) are processed.
 * Specials that cannot be resolved are marked as 'skipped'.
 *
 * The SpecificPrice is created with:
 *  - price reduction as a fixed amount (reduction_type = 'amount')
 *  - from_quantity = 1
 *  - id_shop = 0 (all shops)
 *  - id_currency = 0 (all currencies)
 *  - id_country = 0 (all countries)
 *  - id_group = 0 (all groups)
 *  - id_customer = 0 (all customers)
 *
 * The reduction amount is computed as: products_price - specials_new_products_price
 * (both prices without VAT).
 */
class MtbOscSpecialsImporter
{
    /**
     * Process a batch of pending staged specials.
     *
     * @param int $batchSize
     * @return array ['imported' => int, 'skipped' => int, 'errors' => int]
     */
    public function importBatch($batchSize = 50)
    {
        $batchSize = max(1, (int) $batchSize);
        $prefix = _DB_PREFIX_;

        $rows = Db::getInstance()->executeS(
            "SELECT s.*, p.`products_price`
            FROM `{$prefix}mtb_osc_specials` s
            LEFT JOIN `{$prefix}mtb_osc_product` p
                ON p.`osc_products_id` = s.`osc_products_id`
            WHERE s.`import_status` = 'pending'
            ORDER BY s.`id` ASC
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
                if ($this->importOne($row)) {
                    ++$imported;
                } else {
                    ++$skipped;
                }
            } catch (Exception $e) {
                ++$errors;
                MtbModelImporter::log(
                    'OSC specials import error (osc_specials_id=' . (int) $row['osc_specials_id'] . '): '
                    . $e->getMessage(),
                    'error'
                );
                $this->markStatus((int) $row['id'], 'skipped');
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Import a single specials row as a SpecificPrice.
     *
     * @param array $row Combined row from mtb_osc_specials + mtb_osc_product.
     * @return bool True if SpecificPrice was created, false if skipped.
     * @throws Exception On critical errors.
     */
    public function importOne(array $row)
    {
        $oscProductsId = (int) $row['osc_products_id'];
        $psProductId = $this->resolveProductId($oscProductsId);

        if ($psProductId <= 0) {
            // Product not yet imported – leave pending for later batch
            return false;
        }

        $regularPrice = (float) ($row['products_price'] ?? 0);
        $specialPrice = (float) $row['specials_new_products_price'];

        if ($specialPrice <= 0 || $specialPrice >= $regularPrice) {
            $this->markStatus((int) $row['id'], 'skipped');

            return false;
        }

        $reduction = $regularPrice - $specialPrice;

        $specificPrice = new SpecificPrice();
        $specificPrice->id_product = $psProductId;
        $specificPrice->id_shop = 0;
        $specificPrice->id_currency = 0;
        $specificPrice->id_country = 0;
        $specificPrice->id_group = 0;
        $specificPrice->id_customer = 0;
        $specificPrice->id_product_attribute = 0;
        $specificPrice->reduction = round($reduction, 6);
        $specificPrice->reduction_type = 'amount';
        $specificPrice->reduction_tax = 0; // reduction is on net price (without VAT)
        $specificPrice->from_quantity = 1;
        $specificPrice->price = -1; // -1 = use product base price
        $specificPrice->from = '0000-00-00 00:00:00';
        $specificPrice->to = '0000-00-00 00:00:00';

        $expiresDate = trim((string) ($row['expires_date'] ?? ''));

        if ($expiresDate !== '' && $expiresDate !== '0000-00-00') {
            $specificPrice->to = $expiresDate . ' 23:59:59';
        }

        if (!$specificPrice->add()) {
            throw new Exception('Failed to create SpecificPrice for ps_product_id=' . $psProductId);
        }

        $this->markStatus((int) $row['id'], 'imported');

        MtbModelImporter::log(
            'OSC special imported as SpecificPrice',
            'info',
            [
                'osc_specials_id' => (int) $row['osc_specials_id'],
                'ps_product_id' => $psProductId,
                'reduction' => $reduction,
            ]
        );

        return true;
    }

    /**
     * Look up the PS product ID via the product map.
     *
     * @param int $oscProductsId
     * @return int PS id_product, or 0 if not found.
     */
    protected function resolveProductId($oscProductsId)
    {
        $row = Db::getInstance()->getRow(
            "SELECT `ps_id_product`
            FROM `" . _DB_PREFIX_ . "mtb_osc_product_map`
            WHERE `osc_products_id` = " . (int) $oscProductsId
        );

        return $row ? (int) $row['ps_id_product'] : 0;
    }

    /**
     * Update the import_status on a specials staging row.
     *
     * @param int    $id
     * @param string $status 'imported' | 'skipped'
     * @return void
     */
    protected function markStatus($id, $status)
    {
        Db::getInstance()->update(
            MtbModelImporter::TABLE_OSC_SPECIALS,
            ['import_status' => pSQL($status)],
            '`id` = ' . (int) $id
        );
    }
}
