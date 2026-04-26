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

class AdminMtbImportDashboardController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('MTB Import – Dashboard');
    }

    /**
     * @return string
     */
    public function renderView()
    {
        $prefix = _DB_PREFIX_;

        $counts = [
            'new' => (int) Db::getInstance()->getValue(
                "SELECT COUNT(*) FROM `{$prefix}mtb_import_product` WHERE `status` = 'new'"
            ),
            'changed' => (int) Db::getInstance()->getValue(
                "SELECT COUNT(*) FROM `{$prefix}mtb_import_product` WHERE `status` = 'changed'"
            ),
            'ready' => (int) Db::getInstance()->getValue(
                "SELECT COUNT(*) FROM `{$prefix}mtb_import_product` WHERE `status` = 'ready'"
            ),
            'imported' => (int) Db::getInstance()->getValue(
                "SELECT COUNT(*) FROM `{$prefix}mtb_import_product` WHERE `status` = 'imported'"
            ),
        ];

        $recentLogs = Db::getInstance()->executeS(
            "SELECT `level`, `message`, `created_at`
            FROM `{$prefix}mtb_import_log`
            ORDER BY `created_at` DESC
            LIMIT 10"
        );

        $this->context->smarty->assign([
            'counts' => $counts,
            'recentLogs' => is_array($recentLogs) ? $recentLogs : [],
            'catalogUrl' => $this->context->link->getAdminLink('AdminMtbImportCatalog'),
            'dealerUrl' => $this->context->link->getAdminLink('AdminMtbImportDealer'),
            'productsUrl' => $this->context->link->getAdminLink('AdminMtbImportProducts'),
            'settingsUrl' => $this->context->link->getAdminLink('AdminMtbImportSettings'),
            'logUrl' => $this->context->link->getAdminLink('AdminMtbImportLog'),
        ]);

        return $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'mtbmodelimporter/views/templates/admin/dashboard.tpl'
        );
    }

    /**
     * @return void
     */
    public function initContent()
    {
        $this->content = $this->renderView();
        parent::initContent();
    }
}
