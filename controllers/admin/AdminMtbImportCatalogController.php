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

class AdminMtbImportCatalogController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('MTB Import – Public Catalog');
    }

    /**
     * @return void
     */
    public function initContent()
    {
        $action = Tools::getValue('action');

        if ($action === 'sync' && Tools::isSubmit('sync')) {
            $this->processSyncAction();
        }

        $prefix = _DB_PREFIX_;
        $scale = pSQL((string) Tools::getValue('scale', ''));
        $status = pSQL((string) Tools::getValue('filter_status', ''));

        $whereClause = '1';

        if (!empty($scale) && in_array($scale, MtbModelImporter::SCALES, true)) {
            $whereClause .= " AND p.`scale` = '" . $scale . "'";
        }

        if (!empty($status) && in_array($status, [
            MtbModelImporter::STATUS_NEW,
            MtbModelImporter::STATUS_CHANGED,
            MtbModelImporter::STATUS_READY,
            MtbModelImporter::STATUS_IMPORTED,
        ], true)) {
            $whereClause .= " AND p.`status` = '" . $status . "'";
        }

        $products = Db::getInstance()->executeS(
            "SELECT p.*, pl.`name` AS lang_name, pl.`description` AS lang_description
            FROM `{$prefix}mtb_import_product` p
            LEFT JOIN `{$prefix}mtb_import_product_lang` pl
                ON (p.`id` = pl.`id_product_import` AND pl.`id_lang` = " . (int) $this->context->language->id . ")
            WHERE {$whereClause}
            ORDER BY p.`scale` ASC, p.`supplier_raw_name` ASC
            LIMIT 100"
        );

        $this->context->smarty->assign([
            'products' => is_array($products) ? $products : [],
            'scales' => MtbModelImporter::SCALES,
            'currentScale' => Tools::getValue('scale', ''),
            'currentStatus' => Tools::getValue('filter_status', ''),
            'syncUrl' => $this->context->link->getAdminLink('AdminMtbImportCatalog'),
            'token' => $this->token,
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'mtbmodelimporter/views/templates/admin/catalog.tpl'
        );

        parent::initContent();
    }

    /**
     * @return void
     */
    protected function processSyncAction()
    {
        $scale = pSQL((string) Tools::getValue('scale', ''));

        if (!empty($scale) && !in_array($scale, MtbModelImporter::SCALES, true)) {
            $this->errors[] = $this->module->l('Invalid scale selected.');

            return;
        }

        $scraper = new MtbPublicCatalogScraper();
        $scales = !empty($scale) ? [$scale] : MtbModelImporter::SCALES;

        foreach ($scales as $s) {
            try {
                $result = $scraper->sync($s);
                MtbModelImporter::log(
                    'Catalog sync completed for scale ' . $s,
                    'info',
                    ['count' => $result['count'] ?? 0, 'changed' => $result['changed'] ?? 0]
                );
                $this->confirmations[] = sprintf(
                    $this->module->l('Sync complete for scale %s: %d products, %d changed.'),
                    $s,
                    (int) ($result['count'] ?? 0),
                    (int) ($result['changed'] ?? 0)
                );
            } catch (Exception $e) {
                MtbModelImporter::log('Catalog sync error: ' . $e->getMessage(), 'error');
                $this->errors[] = $this->module->l('Sync error: ') . $e->getMessage();
            }
        }
    }
}
