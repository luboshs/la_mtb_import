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
 * Redirect controller – lists all redirect records in mtb_osc_redirect.
 *
 * Displays product and category redirects for use in .htaccess or
 * a front controller that performs 301 redirects.
 */
class AdminMtbOscRedirectController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('OSC – Redirects');
    }

    /**
     * @return void
     */
    public function initContent()
    {
        $type = pSQL((string) Tools::getValue('filter_type', ''));

        $prefix = _DB_PREFIX_;
        $where = '1';

        if ($type === 'product' || $type === 'category') {
            $where = "`type` = '" . $type . "'";
        }

        $redirects = Db::getInstance()->executeS(
            "SELECT *
            FROM `{$prefix}mtb_osc_redirect`
            WHERE {$where}
            ORDER BY `type` ASC, `osc_id` ASC"
        );

        $this->context->smarty->assign([
            'redirects' => is_array($redirects) ? $redirects : [],
            'filterType' => Tools::getValue('filter_type', ''),
            'redirectUrl' => $this->context->link->getAdminLink('AdminMtbOscRedirect'),
            'token' => $this->token,
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'mtbmodelimporter/views/templates/admin/osc_redirect.tpl'
        );

        parent::initContent();
    }
}
