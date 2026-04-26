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
 * Manufacturer Map controller – manages osCommerce → PS manufacturer mappings.
 *
 * Lists all discovered osCommerce manufacturer names and lets the admin:
 *  - Assign an existing PS manufacturer (by selecting from a dropdown).
 *  - Leave the mapping empty to let the import auto-create a new manufacturer.
 */
class AdminMtbOscManufacturerMapController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('OSC – Brand Map');
    }

    /**
     * @return void
     */
    public function initContent()
    {
        if (Tools::isSubmit('submitManufacturerMap')) {
            $this->processSave();
        }

        $prefix = _DB_PREFIX_;

        $mappings = Db::getInstance()->executeS(
            "SELECT *
            FROM `{$prefix}mtb_osc_manufacturer_map`
            ORDER BY `osc_manufacturers_name` ASC"
        );

        $psManufacturers = Manufacturer::getManufacturers();

        $this->context->smarty->assign([
            'mappings' => is_array($mappings) ? $mappings : [],
            'psManufacturers' => is_array($psManufacturers) ? $psManufacturers : [],
            'mfrMapUrl' => $this->context->link->getAdminLink('AdminMtbOscManufacturerMap'),
            'token' => $this->token,
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'mtbmodelimporter/views/templates/admin/osc_manufacturer_map.tpl'
        );

        parent::initContent();
    }

    /**
     * Save manufacturer map entries.
     *
     * @return void
     */
    protected function processSave()
    {
        $prefix = _DB_PREFIX_;

        $mapIds = Tools::getValue('map_id', []);
        $psIds = Tools::getValue('ps_manufacturer_id', []);

        if (!is_array($mapIds)) {
            return;
        }

        foreach ($mapIds as $index => $mapId) {
            $mapId = (int) $mapId;

            if ($mapId <= 0) {
                continue;
            }

            $psId = isset($psIds[$index]) ? (int) $psIds[$index] : 0;

            Db::getInstance()->execute(
                "UPDATE `{$prefix}mtb_osc_manufacturer_map`
                SET `ps_id_manufacturer` = " . ($psId > 0 ? $psId : 'NULL') . "
                WHERE `id` = " . $mapId
            );
        }

        $this->confirmations[] = $this->module->l('Manufacturer mappings saved.');
    }
}
