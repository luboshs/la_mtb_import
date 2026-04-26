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
 * Category Map controller – manages osCommerce → PS category mappings.
 *
 * Lists all discovered osCommerce categories and lets the admin:
 *  - Assign a PS category ID to each osC category.
 *  - Toggle the "ignore binding" flag to exclude a category from import.
 */
class AdminMtbOscCategoryMapController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('OSC – Category Map');
    }

    /**
     * @return void
     */
    public function initContent()
    {
        if (Tools::isSubmit('submitCategoryMap')) {
            $this->processSave();
        }

        $prefix = _DB_PREFIX_;

        $mappings = Db::getInstance()->executeS(
            "SELECT *
            FROM `{$prefix}mtb_osc_category_map`
            ORDER BY `osc_categories_id` ASC"
        );

        $psCategories = Category::getCategories(
            $this->context->language->id,
            false,
            false
        );

        $this->context->smarty->assign([
            'mappings' => is_array($mappings) ? $mappings : [],
            'psCategories' => is_array($psCategories) ? $this->flattenCategories($psCategories) : [],
            'categoryMapUrl' => $this->context->link->getAdminLink('AdminMtbOscCategoryMap'),
            'token' => $this->token,
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'mtbmodelimporter/views/templates/admin/osc_category_map.tpl'
        );

        parent::initContent();
    }

    /**
     * Save all category map entries submitted in the form.
     *
     * @return void
     */
    protected function processSave()
    {
        $prefix = _DB_PREFIX_;

        $oscIds = Tools::getValue('osc_cat_id', []);
        $psCatIds = Tools::getValue('ps_cat_id', []);
        $ignoreFlags = Tools::getValue('ignore_binding', []);

        if (!is_array($oscIds)) {
            return;
        }

        foreach ($oscIds as $index => $oscId) {
            $oscId = (int) $oscId;

            if ($oscId <= 0) {
                continue;
            }

            $psCatId = isset($psCatIds[$index]) ? (int) $psCatIds[$index] : 0;
            $ignore = isset($ignoreFlags[$index]) ? 1 : 0;

            Db::getInstance()->execute(
                "UPDATE `{$prefix}mtb_osc_category_map`
                SET
                    `ps_id_category` = " . ($psCatId > 0 ? $psCatId : 'NULL') . ",
                    `ignore_binding` = " . $ignore . "
                WHERE `osc_categories_id` = " . $oscId
            );
        }

        $this->confirmations[] = $this->module->l('Category mappings saved.');
    }

    /**
     * Flatten the nested PS category tree into a simple list.
     *
     * @param array $categories Nested array from Category::getCategories().
     * @return array Flat list of ['id_category' => int, 'name' => string].
     */
    protected function flattenCategories(array $categories)
    {
        $flat = [];

        foreach ($categories as $topId => $topData) {
            if (!isset($topData[0])) {
                continue;
            }

            foreach ($topData[0] as $cat) {
                $flat[] = [
                    'id_category' => (int) $cat['id_category'],
                    'name' => $cat['name'],
                ];
            }
        }

        usort($flat, function ($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });

        return $flat;
    }
}
