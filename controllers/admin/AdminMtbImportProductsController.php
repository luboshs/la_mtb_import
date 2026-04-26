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

class AdminMtbImportProductsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('MTB Import – Suggestions');
    }

    /**
     * @return void
     */
    public function initContent()
    {
        if (Tools::isSubmit('submitImport')) {
            $this->processImport();
        }

        if (Tools::isSubmit('submitEditName')) {
            $this->processEditName();
        }

        if (Tools::isSubmit('submitApprove')) {
            $this->processApprove();
        }

        $prefix = _DB_PREFIX_;
        $status = pSQL((string) Tools::getValue('filter_status', ''));
        $scale = pSQL((string) Tools::getValue('filter_scale', ''));

        $whereClause = '1';

        if (!empty($status) && in_array($status, [
            MtbModelImporter::STATUS_NEW,
            MtbModelImporter::STATUS_CHANGED,
            MtbModelImporter::STATUS_READY,
            MtbModelImporter::STATUS_IMPORTED,
        ], true)) {
            $whereClause .= " AND p.`status` = '" . $status . "'";
        }

        if (!empty($scale) && in_array($scale, MtbModelImporter::SCALES, true)) {
            $whereClause .= " AND p.`scale` = '" . $scale . "'";
        }

        $products = Db::getInstance()->executeS(
            "SELECT p.*, pl.`name` AS lang_name, pl.`status` AS lang_status
            FROM `{$prefix}mtb_import_product` p
            LEFT JOIN `{$prefix}mtb_import_product_lang` pl
                ON (p.`id` = pl.`id_product_import` AND pl.`id_lang` = " . (int) $this->context->language->id . ")
            WHERE {$whereClause}
            ORDER BY p.`updated_at` DESC
            LIMIT 200"
        );

        $categories = Category::getCategories(
            $this->context->language->id,
            false,
            false
        );

        $manufacturers = Manufacturer::getManufacturers();

        $this->context->smarty->assign([
            'products' => is_array($products) ? $products : [],
            'scales' => MtbModelImporter::SCALES,
            'filterStatus' => Tools::getValue('filter_status', ''),
            'filterScale' => Tools::getValue('filter_scale', ''),
            'categories' => is_array($categories) ? $categories : [],
            'manufacturers' => is_array($manufacturers) ? $manufacturers : [],
            'productsUrl' => $this->context->link->getAdminLink('AdminMtbImportProducts'),
            'token' => $this->token,
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'mtbmodelimporter/views/templates/admin/products.tpl'
        );

        parent::initContent();
    }

    /**
     * @return void
     */
    protected function processImport()
    {
        $importId = (int) Tools::getValue('id_import');
        $idCategory = (int) Tools::getValue('id_category');
        $idManufacturer = (int) Tools::getValue('id_manufacturer');

        if ($importId <= 0 || $idCategory <= 0) {
            $this->errors[] = $this->module->l('Please select a valid import record, category and brand.');

            return;
        }

        $service = new MtbProductImportService();

        try {
            $idProduct = $service->import($importId, $idCategory, $idManufacturer);

            if ($idProduct > 0) {
                $this->confirmations[] = sprintf(
                    $this->module->l('Product imported successfully with ID %d.'),
                    $idProduct
                );
            } else {
                $this->errors[] = $this->module->l('Product import failed.');
            }
        } catch (Exception $e) {
            MtbModelImporter::log('Import error: ' . $e->getMessage(), 'error');
            $this->errors[] = $this->module->l('Import error: ') . $e->getMessage();
        }
    }

    /**
     * @return void
     */
    protected function processEditName()
    {
        $importId = (int) Tools::getValue('id_import');
        $adminName = (string) Tools::getValue('admin_name', '');

        if ($importId <= 0) {
            $this->errors[] = $this->module->l('Invalid import record.');

            return;
        }

        $result = Db::getInstance()->update(
            MtbModelImporter::TABLE_PRODUCT,
            [
                'admin_name' => pSQL($adminName),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            '`id` = ' . $importId
        );

        if ($result) {
            $this->confirmations[] = $this->module->l('Product name updated.');
        } else {
            $this->errors[] = $this->module->l('Failed to update product name.');
        }
    }

    /**
     * @return void
     */
    protected function processApprove()
    {
        $importId = (int) Tools::getValue('id_import');

        if ($importId <= 0) {
            $this->errors[] = $this->module->l('Invalid import record.');

            return;
        }

        $result = Db::getInstance()->update(
            MtbModelImporter::TABLE_PRODUCT,
            [
                'status' => MtbModelImporter::STATUS_READY,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            '`id` = ' . $importId
        );

        if ($result) {
            $this->confirmations[] = $this->module->l('Product marked as ready for import.');
        } else {
            $this->errors[] = $this->module->l('Failed to update product status.');
        }
    }
}
