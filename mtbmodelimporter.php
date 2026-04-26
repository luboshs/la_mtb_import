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

require_once __DIR__ . '/src/Helper/EanNormalizer.php';
require_once __DIR__ . '/src/Service/DealerPasteParser.php';
require_once __DIR__ . '/src/Service/ProductNameGenerator.php';
require_once __DIR__ . '/src/Service/OpenAiTranslator.php';
require_once __DIR__ . '/src/Service/PublicCatalogScraper.php';
require_once __DIR__ . '/src/Service/ProductImportService.php';
require_once __DIR__ . '/src/Service/ImageImportService.php';
require_once __DIR__ . '/src/Service/OscCsvReader.php';
require_once __DIR__ . '/src/Service/OscStagingImporter.php';
require_once __DIR__ . '/src/Service/OscManufacturerMapper.php';
require_once __DIR__ . '/src/Service/OscCategoryMapper.php';
require_once __DIR__ . '/src/Service/OscProductImporter.php';
require_once __DIR__ . '/src/Service/OscSpecialsImporter.php';
require_once __DIR__ . '/src/Service/OscRedirectManager.php';

class MtbModelImporter extends Module
{
    const CONFIG_OPENAI_API_KEY = 'MTB_OPENAI_API_KEY';
    const CONFIG_OPENAI_MODEL = 'MTB_OPENAI_MODEL';
    const CONFIG_CRON_TOKEN = 'MTB_CRON_TOKEN';
    const CONFIG_TRANSLATION_ENABLED = 'MTB_TRANSLATION_ENABLED';

    // osCommerce import configuration
    const CONFIG_OSC_BASE_IMAGE_URL = 'MTB_OSC_BASE_IMAGE_URL';
    const CONFIG_OSC_FALLBACK_CATEGORY = 'MTB_OSC_FALLBACK_CATEGORY';
    const CONFIG_OSC_BATCH_SIZE = 'MTB_OSC_BATCH_SIZE';
    const CONFIG_OSC_TAX_MAP_23 = 'MTB_OSC_TAX_MAP_23';
    const CONFIG_OSC_TAX_MAP_5 = 'MTB_OSC_TAX_MAP_5';

    const TABLE_PRODUCT = 'mtb_import_product';
    const TABLE_PRODUCT_LANG = 'mtb_import_product_lang';
    const TABLE_LOG = 'mtb_import_log';

    // osCommerce staging / mapping tables
    const TABLE_OSC_PRODUCT = 'mtb_osc_product';
    const TABLE_OSC_SPECIALS = 'mtb_osc_specials';
    const TABLE_OSC_CATEGORY_MAP = 'mtb_osc_category_map';
    const TABLE_OSC_MANUFACTURER_MAP = 'mtb_osc_manufacturer_map';
    const TABLE_OSC_PRODUCT_MAP = 'mtb_osc_product_map';
    const TABLE_OSC_REDIRECT = 'mtb_osc_redirect';

    const STATUS_NEW = 'new';
    const STATUS_CHANGED = 'changed';
    const STATUS_READY = 'ready';
    const STATUS_IMPORTED = 'imported';

    const LANG_STATUS_SOURCE = 'source';
    const LANG_STATUS_AUTO = 'auto';
    const LANG_STATUS_EDITED = 'edited';
    const LANG_STATUS_APPROVED = 'approved';

    const SCALES = ['H0', 'TT', 'N'];

    public function __construct()
    {
        $this->name = 'mtbmodelimporter';
        $this->tab = 'administration';
        $this->version = '2.0.0';
        $this->author = 'luboshs';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.2.0', 'max' => '8.9.99'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('MTB Model Importer');
        $this->description = $this->l(
            'Import and manage MTB model products from public catalog and dealer data.'
        );
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
    }

    /**
     * @return bool
     */
    public function install()
    {
        return parent::install()
            && $this->installTabs()
            && $this->installDb()
            && $this->installConfig()
            && $this->registerHook('actionAdminControllerSetMedia');
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallTabs()
            && $this->uninstallDb()
            && $this->uninstallConfig();
    }

    /**
     * @return bool
     */
    protected function installTabs()
    {
        $tabs = [
            [
                'class_name' => 'AdminMtbImportDashboard',
                'name' => 'MTB Import',
                'icon' => 'train',
                'parent' => 'IMPROVE',
                'wording' => 'MTB Import',
                'wording_domain' => 'Modules.Mtbmodelimporter.Admin',
            ],
            [
                'class_name' => 'AdminMtbImportCatalog',
                'name' => 'Public Catalog',
                'parent' => 'AdminMtbImportDashboard',
                'wording' => 'Public Catalog',
                'wording_domain' => 'Modules.Mtbmodelimporter.Admin',
            ],
            [
                'class_name' => 'AdminMtbImportDealer',
                'name' => 'Dealer Import',
                'parent' => 'AdminMtbImportDashboard',
                'wording' => 'Dealer Import',
                'wording_domain' => 'Modules.Mtbmodelimporter.Admin',
            ],
            [
                'class_name' => 'AdminMtbImportProducts',
                'name' => 'Suggestions',
                'parent' => 'AdminMtbImportDashboard',
                'wording' => 'Suggestions',
                'wording_domain' => 'Modules.Mtbmodelimporter.Admin',
            ],
            [
                'class_name' => 'AdminMtbImportSettings',
                'name' => 'Settings',
                'parent' => 'AdminMtbImportDashboard',
                'wording' => 'Settings',
                'wording_domain' => 'Modules.Mtbmodelimporter.Admin',
            ],
            [
                'class_name' => 'AdminMtbImportLog',
                'name' => 'Log',
                'parent' => 'AdminMtbImportDashboard',
                'wording' => 'Log',
                'wording_domain' => 'Modules.Mtbmodelimporter.Admin',
            ],
            [
                'class_name' => 'AdminMtbOscImport',
                'name' => 'OSC Import',
                'parent' => 'AdminMtbImportDashboard',
                'wording' => 'OSC Import',
                'wording_domain' => 'Modules.Mtbmodelimporter.Admin',
            ],
            [
                'class_name' => 'AdminMtbOscCategoryMap',
                'name' => 'Category Map',
                'parent' => 'AdminMtbOscImport',
                'wording' => 'Category Map',
                'wording_domain' => 'Modules.Mtbmodelimporter.Admin',
            ],
            [
                'class_name' => 'AdminMtbOscManufacturerMap',
                'name' => 'Brand Map',
                'parent' => 'AdminMtbOscImport',
                'wording' => 'Brand Map',
                'wording_domain' => 'Modules.Mtbmodelimporter.Admin',
            ],
            [
                'class_name' => 'AdminMtbOscRedirect',
                'name' => 'Redirects',
                'parent' => 'AdminMtbOscImport',
                'wording' => 'Redirects',
                'wording_domain' => 'Modules.Mtbmodelimporter.Admin',
            ],
        ];

        foreach ($tabs as $tabData) {
            $tab = new Tab();
            $tab->active = 1;
            $tab->class_name = $tabData['class_name'];
            $tab->module = $this->name;
            $tab->id_parent = (int) Tab::getIdFromClassName($tabData['parent']);
            $tab->icon = $tabData['icon'] ?? '';
            $tab->wording = $tabData['wording'];
            $tab->wording_domain = $tabData['wording_domain'];

            foreach (Language::getLanguages() as $lang) {
                $tab->name[$lang['id_lang']] = $tabData['name'];
            }

            if (!$tab->add()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function uninstallTabs()
    {
        $tabClassNames = [
            'AdminMtbOscRedirect',
            'AdminMtbOscManufacturerMap',
            'AdminMtbOscCategoryMap',
            'AdminMtbOscImport',
            'AdminMtbImportLog',
            'AdminMtbImportSettings',
            'AdminMtbImportProducts',
            'AdminMtbImportDealer',
            'AdminMtbImportCatalog',
            'AdminMtbImportDashboard',
        ];

        foreach ($tabClassNames as $className) {
            $idTab = (int) Tab::getIdFromClassName($className);

            if ($idTab > 0) {
                $tab = new Tab($idTab);

                if (!$tab->delete()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function installDb()
    {
        $engine = _MYSQL_ENGINE_;
        $prefix = _DB_PREFIX_;

        $queries = [
            "CREATE TABLE IF NOT EXISTS `{$prefix}mtb_import_product` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `id_product` int(10) unsigned DEFAULT NULL,
                `scale` varchar(10) NOT NULL DEFAULT '',
                `supplier_raw_name` varchar(255) NOT NULL DEFAULT '',
                `supplier_reference` varchar(100) DEFAULT NULL,
                `generated_name` varchar(255) DEFAULT NULL,
                `admin_name` varchar(255) DEFAULT NULL,
                `source_url` text DEFAULT NULL,
                `source_hash` varchar(64) DEFAULT NULL,
                `dealer_price` decimal(20,6) DEFAULT NULL,
                `ean_original` varchar(50) DEFAULT NULL,
                `ean_normalized` varchar(50) DEFAULT NULL,
                `dealer_category` varchar(255) DEFAULT NULL,
                `order_status` varchar(50) DEFAULT NULL,
                `dealer_note` text DEFAULT NULL,
                `has_bearings` tinyint(1) NOT NULL DEFAULT 0,
                `has_integrated_dcc` tinyint(1) NOT NULL DEFAULT 0,
                `status` enum('new','changed','ready','imported') NOT NULL DEFAULT 'new',
                `created_at` datetime NOT NULL,
                `updated_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_scale_name` (`scale`, `supplier_raw_name`(191))
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `{$prefix}mtb_import_product_lang` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `id_product_import` int(10) unsigned NOT NULL,
                `id_lang` int(10) unsigned NOT NULL,
                `name` varchar(255) DEFAULT NULL,
                `description` text DEFAULT NULL,
                `status` enum('source','auto','edited','approved') NOT NULL DEFAULT 'source',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_import_lang` (`id_product_import`, `id_lang`),
                KEY `fk_product_import` (`id_product_import`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `{$prefix}mtb_import_log` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `level` varchar(20) NOT NULL DEFAULT 'info',
                `message` text NOT NULL,
                `context` text DEFAULT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_level` (`level`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `{$prefix}mtb_osc_product` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `osc_products_id` int(10) unsigned NOT NULL,
                `products_model` varchar(64) DEFAULT NULL,
                `manufacturers_name` varchar(255) DEFAULT NULL,
                `products_name` varchar(255) NOT NULL DEFAULT '',
                `products_description` text DEFAULT NULL,
                `products_price` decimal(20,6) DEFAULT NULL,
                `products_tax_class_id` int(10) unsigned DEFAULT NULL,
                `products_image` varchar(255) DEFAULT NULL,
                `products_date_available` date DEFAULT NULL,
                `categories_ids` varchar(255) DEFAULT NULL,
                `availability` varchar(100) DEFAULT NULL,
                `is_new` tinyint(1) NOT NULL DEFAULT 0,
                `is_optimum` tinyint(1) NOT NULL DEFAULT 0,
                `subimage1` varchar(255) DEFAULT NULL,
                `subimage2` varchar(255) DEFAULT NULL,
                `subimage3` varchar(255) DEFAULT NULL,
                `subimage4` varchar(255) DEFAULT NULL,
                `subimage5` varchar(255) DEFAULT NULL,
                `subimage6` varchar(255) DEFAULT NULL,
                `import_status` enum('pending','imported','skipped') NOT NULL DEFAULT 'pending',
                `created_at` datetime NOT NULL,
                `updated_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_osc_products_id` (`osc_products_id`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `{$prefix}mtb_osc_specials` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `osc_specials_id` int(10) unsigned NOT NULL,
                `osc_products_id` int(10) unsigned NOT NULL,
                `specials_new_products_price` decimal(20,6) DEFAULT NULL,
                `specials_date_added` date DEFAULT NULL,
                `expires_date` date DEFAULT NULL,
                `import_status` enum('pending','imported','skipped') NOT NULL DEFAULT 'pending',
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_osc_specials_id` (`osc_specials_id`),
                KEY `idx_osc_products_id` (`osc_products_id`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `{$prefix}mtb_osc_category_map` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `osc_categories_id` int(10) unsigned NOT NULL,
                `osc_category_name` varchar(255) NOT NULL DEFAULT '',
                `ps_id_category` int(10) unsigned DEFAULT NULL,
                `ignore_binding` tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_osc_categories_id` (`osc_categories_id`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `{$prefix}mtb_osc_manufacturer_map` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `osc_manufacturers_name` varchar(255) NOT NULL,
                `ps_id_manufacturer` int(10) unsigned DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_osc_manufacturers_name` (`osc_manufacturers_name`(191))
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `{$prefix}mtb_osc_product_map` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `osc_products_id` int(10) unsigned NOT NULL,
                `ps_id_product` int(10) unsigned NOT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_osc_products_id` (`osc_products_id`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `{$prefix}mtb_osc_redirect` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `type` enum('product','category') NOT NULL,
                `osc_id` int(10) unsigned NOT NULL,
                `osc_url` varchar(255) NOT NULL DEFAULT '',
                `ps_url` varchar(255) NOT NULL DEFAULT '',
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_type_osc_id` (`type`, `osc_id`)
            ) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function uninstallDb()
    {
        $prefix = _DB_PREFIX_;

        $queries = [
            "DROP TABLE IF EXISTS `{$prefix}mtb_osc_redirect`",
            "DROP TABLE IF EXISTS `{$prefix}mtb_osc_product_map`",
            "DROP TABLE IF EXISTS `{$prefix}mtb_osc_manufacturer_map`",
            "DROP TABLE IF EXISTS `{$prefix}mtb_osc_category_map`",
            "DROP TABLE IF EXISTS `{$prefix}mtb_osc_specials`",
            "DROP TABLE IF EXISTS `{$prefix}mtb_osc_product`",
            "DROP TABLE IF EXISTS `{$prefix}mtb_import_product_lang`",
            "DROP TABLE IF EXISTS `{$prefix}mtb_import_log`",
            "DROP TABLE IF EXISTS `{$prefix}mtb_import_product`",
        ];

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function installConfig()
    {
        return Configuration::updateValue(self::CONFIG_OPENAI_API_KEY, '')
            && Configuration::updateValue(self::CONFIG_OPENAI_MODEL, 'gpt-4.1-mini')
            && Configuration::updateValue(self::CONFIG_CRON_TOKEN, $this->generateCronToken())
            && Configuration::updateValue(self::CONFIG_TRANSLATION_ENABLED, 0)
            && Configuration::updateValue(self::CONFIG_OSC_BASE_IMAGE_URL, '')
            && Configuration::updateValue(self::CONFIG_OSC_FALLBACK_CATEGORY, 0)
            && Configuration::updateValue(self::CONFIG_OSC_BATCH_SIZE, 50)
            && Configuration::updateValue(self::CONFIG_OSC_TAX_MAP_23, '')
            && Configuration::updateValue(self::CONFIG_OSC_TAX_MAP_5, '');
    }

    /**
     * @return bool
     */
    protected function uninstallConfig()
    {
        return Configuration::deleteByName(self::CONFIG_OPENAI_API_KEY)
            && Configuration::deleteByName(self::CONFIG_OPENAI_MODEL)
            && Configuration::deleteByName(self::CONFIG_CRON_TOKEN)
            && Configuration::deleteByName(self::CONFIG_TRANSLATION_ENABLED)
            && Configuration::deleteByName(self::CONFIG_OSC_BASE_IMAGE_URL)
            && Configuration::deleteByName(self::CONFIG_OSC_FALLBACK_CATEGORY)
            && Configuration::deleteByName(self::CONFIG_OSC_BATCH_SIZE)
            && Configuration::deleteByName(self::CONFIG_OSC_TAX_MAP_23)
            && Configuration::deleteByName(self::CONFIG_OSC_TAX_MAP_5);
    }

    /**
     * @return string
     */
    protected function generateCronToken()
    {
        return bin2hex(random_bytes(20));
    }

    /**
     * @param array $params
     * @return void
     */
    public function hookActionAdminControllerSetMedia($params)
    {
        if ($this->context->controller->controller_name === 'AdminMtbImportDashboard'
            || $this->context->controller->controller_name === 'AdminMtbImportCatalog'
            || $this->context->controller->controller_name === 'AdminMtbImportDealer'
            || $this->context->controller->controller_name === 'AdminMtbImportProducts'
            || $this->context->controller->controller_name === 'AdminMtbImportSettings'
            || $this->context->controller->controller_name === 'AdminMtbImportLog'
            || $this->context->controller->controller_name === 'AdminMtbOscImport'
            || $this->context->controller->controller_name === 'AdminMtbOscCategoryMap'
            || $this->context->controller->controller_name === 'AdminMtbOscManufacturerMap'
            || $this->context->controller->controller_name === 'AdminMtbOscRedirect'
        ) {
            $this->context->controller->addJS(
                $this->_path . 'views/js/mtbmodelimporter.js'
            );
        }
    }

    /**
     * Redirect to the settings page when clicking the configure link.
     *
     * @return string
     */
    public function getContent()
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminMtbImportDashboard')
        );
    }

    /**
     * Write an entry to the module log table.
     *
     * @param string $message
     * @param string $level
     * @param array|null $context
     * @return bool
     */
    public static function log($message, $level = 'info', $context = null)
    {
        return Db::getInstance()->insert(
            self::TABLE_LOG,
            [
                'level' => pSQL($level),
                'message' => pSQL($message),
                'context' => $context !== null ? pSQL(json_encode($context)) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
    }
}
