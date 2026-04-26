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

class AdminMtbImportDealerController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('MTB Import – Dealer Import');
    }

    /**
     * @return void
     */
    public function initContent()
    {
        $parsedResults = [];
        $pasteText = '';

        if (Tools::isSubmit('submitAnalyze')) {
            $pasteText = (string) Tools::getValue('dealer_paste', '');
            $parsedResults = $this->processAnalyze($pasteText);
        } elseif (Tools::isSubmit('submitSave')) {
            $pasteText = (string) Tools::getValue('dealer_paste', '');
            $parsedResults = $this->processAnalyze($pasteText);

            if (!empty($parsedResults) && empty($this->errors)) {
                $this->processSave($parsedResults);
            }
        }

        $this->context->smarty->assign([
            'pasteText' => $pasteText,
            'parsedResults' => $parsedResults,
            'dealerUrl' => $this->context->link->getAdminLink('AdminMtbImportDealer'),
            'token' => $this->token,
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'mtbmodelimporter/views/templates/admin/dealer.tpl'
        );

        parent::initContent();
    }

    /**
     * Parse the pasted dealer text.
     *
     * @param string $text
     * @return array
     */
    protected function processAnalyze($text)
    {
        if (empty(trim($text))) {
            $this->errors[] = $this->module->l('Please paste dealer data before analyzing.');

            return [];
        }

        $parser = new MtbDealerPasteParser();

        return $parser->parse($text);
    }

    /**
     * Save parsed dealer records to the import table.
     *
     * @param array $records
     * @return void
     */
    protected function processSave(array $records)
    {
        $saved = 0;
        $skipped = 0;
        $nameGenerator = new MtbProductNameGenerator();
        $eanNormalizer = new MtbEanNormalizer();

        foreach ($records as $record) {
            if (empty($record['supplier_raw_name'])) {
                ++$skipped;

                continue;
            }

            $scale = pSQL((string) ($record['scale'] ?? 'H0'));
            $supplierRawName = pSQL((string) $record['supplier_raw_name']);

            $existing = Db::getInstance()->getRow(
                "SELECT `id`, `status` FROM `" . _DB_PREFIX_ . "mtb_import_product`
                WHERE `scale` = '" . $scale . "' AND `supplier_raw_name` = '" . $supplierRawName . "'"
            );

            $eanOriginal = isset($record['ean']) ? pSQL((string) $record['ean']) : null;
            $eanNormalized = null;

            if (!empty($eanOriginal)) {
                $eanNormalized = pSQL($eanNormalizer->normalize($eanOriginal));
            }

            $generatedName = pSQL($nameGenerator->generate((string) ($record['supplier_raw_name'] ?? '')));
            $now = date('Y-m-d H:i:s');

            $data = [
                'scale' => $scale,
                'supplier_raw_name' => $supplierRawName,
                'supplier_reference' => pSQL((string) ($record['reference'] ?? '')),
                'generated_name' => $generatedName,
                'dealer_price' => (float) ($record['price'] ?? 0),
                'ean_original' => $eanOriginal,
                'ean_normalized' => $eanNormalized,
                'dealer_category' => pSQL((string) ($record['category'] ?? '')),
                'order_status' => pSQL((string) ($record['order_status'] ?? '')),
                'dealer_note' => pSQL((string) ($record['note'] ?? '')),
                'has_bearings' => (int) ($record['has_bearings'] ?? 0),
                'has_integrated_dcc' => (int) ($record['has_integrated_dcc'] ?? 0),
                'updated_at' => $now,
            ];

            if ($existing) {
                $data['status'] = MtbModelImporter::STATUS_CHANGED;
                $result = Db::getInstance()->update(
                    MtbModelImporter::TABLE_PRODUCT,
                    $data,
                    '`id` = ' . (int) $existing['id']
                );
            } else {
                $data['status'] = MtbModelImporter::STATUS_NEW;
                $data['created_at'] = $now;
                $result = Db::getInstance()->insert(MtbModelImporter::TABLE_PRODUCT, $data);
            }

            if ($result) {
                ++$saved;
                MtbModelImporter::log(
                    ($existing ? 'Updated' : 'Added') . ' product: ' . (string) ($record['supplier_raw_name'] ?? ''),
                    'info'
                );
            } else {
                ++$skipped;
                MtbModelImporter::log(
                    'Failed to save product: ' . (string) ($record['supplier_raw_name'] ?? ''),
                    'warning'
                );
            }
        }

        if ($saved > 0) {
            $this->confirmations[] = sprintf(
                $this->module->l('%d product(s) saved successfully.'),
                $saved
            );
        }

        if ($skipped > 0) {
            $this->warnings[] = sprintf(
                $this->module->l('%d record(s) were skipped.'),
                $skipped
            );
        }
    }
}
