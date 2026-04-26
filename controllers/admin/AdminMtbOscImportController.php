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
 * OSC Import controller – CSV upload and batch import.
 *
 * Actions handled:
 *  - submitOscProductsCsv  : upload + stage products CSV
 *  - submitOscSpecialsCsv  : upload + stage specials CSV
 *  - submitOscCategoriesCsv: upload + stage categories CSV
 *  - submitOscBatchImport  : run one batch of staged product imports
 *  - submitOscBatchSpecials: run one batch of staged specials imports
 */
class AdminMtbOscImportController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('OSC Import');
    }

    /**
     * @return void
     */
    public function initContent()
    {
        if (Tools::isSubmit('submitOscProductsCsv')) {
            $this->processProductsCsvUpload();
        }

        if (Tools::isSubmit('submitOscSpecialsCsv')) {
            $this->processSpecialsCsvUpload();
        }

        if (Tools::isSubmit('submitOscCategoriesCsv')) {
            $this->processCategoriesCsvUpload();
        }

        if (Tools::isSubmit('submitOscBatchImport')) {
            $this->processBatchImport();
        }

        if (Tools::isSubmit('submitOscBatchSpecials')) {
            $this->processBatchSpecials();
        }

        $this->context->smarty->assign([
            'oscImportUrl' => $this->context->link->getAdminLink('AdminMtbOscImport'),
            'token' => $this->token,
            'stats' => $this->getStats(),
            'settingsForm' => $this->renderSettingsForm(),
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'mtbmodelimporter/views/templates/admin/osc_import.tpl'
        );

        parent::initContent();
    }

    /**
     * @return void
     */
    protected function processProductsCsvUpload()
    {
        $file = $_FILES['osc_products_csv'] ?? null;

        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->module->l('Please select a valid products CSV file.');

            return;
        }

        $tmpPath = $file['tmp_name'];

        try {
            $reader = new MtbOscCsvReader();
            $importer = new MtbOscStagingImporter($reader);
            $result = $importer->importProducts($tmpPath);

            $this->confirmations[] = sprintf(
                $this->module->l('Products CSV staged: %d inserted, %d skipped.'),
                (int) $result['inserted'],
                (int) $result['skipped']
            );
        } catch (Exception $e) {
            MtbModelImporter::log('OSC products CSV upload error: ' . $e->getMessage(), 'error');
            $this->errors[] = $this->module->l('CSV error: ') . $e->getMessage();
        }
    }

    /**
     * @return void
     */
    protected function processSpecialsCsvUpload()
    {
        $file = $_FILES['osc_specials_csv'] ?? null;

        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->module->l('Please select a valid specials CSV file.');

            return;
        }

        $tmpPath = $file['tmp_name'];

        try {
            $reader = new MtbOscCsvReader();
            $importer = new MtbOscStagingImporter($reader);
            $result = $importer->importSpecials($tmpPath);

            $this->confirmations[] = sprintf(
                $this->module->l('Specials CSV staged: %d inserted, %d skipped.'),
                (int) $result['inserted'],
                (int) $result['skipped']
            );
        } catch (Exception $e) {
            MtbModelImporter::log('OSC specials CSV upload error: ' . $e->getMessage(), 'error');
            $this->errors[] = $this->module->l('CSV error: ') . $e->getMessage();
        }
    }

    /**
     * @return void
     */
    protected function processCategoriesCsvUpload()
    {
        $file = $_FILES['osc_categories_csv'] ?? null;

        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->module->l('Please select a valid categories CSV file.');

            return;
        }

        $tmpPath = $file['tmp_name'];

        try {
            $reader = new MtbOscCsvReader();
            $importer = new MtbOscStagingImporter($reader);
            $result = $importer->importCategories($tmpPath);

            $this->confirmations[] = sprintf(
                $this->module->l('Categories CSV staged: %d inserted, %d updated, %d skipped.'),
                (int) $result['inserted'],
                (int) $result['updated'],
                (int) $result['skipped']
            );
        } catch (Exception $e) {
            MtbModelImporter::log('OSC categories CSV upload error: ' . $e->getMessage(), 'error');
            $this->errors[] = $this->module->l('CSV error: ') . $e->getMessage();
        }
    }

    /**
     * @return void
     */
    protected function processBatchImport()
    {
        $batchSize = (int) Configuration::get(MtbModelImporter::CONFIG_OSC_BATCH_SIZE);

        if ($batchSize <= 0) {
            $batchSize = 50;
        }

        $importer = new MtbOscProductImporter(
            new MtbOscCategoryMapper(),
            new MtbOscManufacturerMapper(),
            new MtbImageImportService(),
            new MtbOscRedirectManager()
        );

        $result = $importer->importBatch($batchSize);

        $this->confirmations[] = sprintf(
            $this->module->l('Batch import complete: %d imported, %d skipped, %d errors.'),
            (int) $result['imported'],
            (int) $result['skipped'],
            (int) $result['errors']
        );
    }

    /**
     * @return void
     */
    protected function processBatchSpecials()
    {
        $batchSize = (int) Configuration::get(MtbModelImporter::CONFIG_OSC_BATCH_SIZE);

        if ($batchSize <= 0) {
            $batchSize = 50;
        }

        $importer = new MtbOscSpecialsImporter();
        $result = $importer->importBatch($batchSize);

        $this->confirmations[] = sprintf(
            $this->module->l('Specials batch complete: %d imported, %d skipped, %d errors.'),
            (int) $result['imported'],
            (int) $result['skipped'],
            (int) $result['errors']
        );
    }

    /**
     * @return void
     */
    protected function processSaveSettings()
    {
        Configuration::updateValue(
            MtbModelImporter::CONFIG_OSC_BASE_IMAGE_URL,
            (string) Tools::getValue(MtbModelImporter::CONFIG_OSC_BASE_IMAGE_URL, '')
        );

        $fallback = (int) Tools::getValue(MtbModelImporter::CONFIG_OSC_FALLBACK_CATEGORY, 0);
        Configuration::updateValue(MtbModelImporter::CONFIG_OSC_FALLBACK_CATEGORY, $fallback);

        $batchSize = max(1, (int) Tools::getValue(MtbModelImporter::CONFIG_OSC_BATCH_SIZE, 50));
        Configuration::updateValue(MtbModelImporter::CONFIG_OSC_BATCH_SIZE, $batchSize);

        $tax23 = preg_replace('/[^0-9,]/', '', (string) Tools::getValue(MtbModelImporter::CONFIG_OSC_TAX_MAP_23, ''));
        Configuration::updateValue(MtbModelImporter::CONFIG_OSC_TAX_MAP_23, $tax23);

        $tax5 = preg_replace('/[^0-9,]/', '', (string) Tools::getValue(MtbModelImporter::CONFIG_OSC_TAX_MAP_5, ''));
        Configuration::updateValue(MtbModelImporter::CONFIG_OSC_TAX_MAP_5, $tax5);

        $this->confirmations[] = $this->module->l('OSC import settings saved.');
    }

    /**
     * Build current staging statistics for the template.
     *
     * @return array
     */
    protected function getStats()
    {
        $prefix = _DB_PREFIX_;

        $productStats = Db::getInstance()->getRow(
            "SELECT
                COUNT(*) AS total,
                SUM(import_status = 'pending') AS pending,
                SUM(import_status = 'imported') AS imported,
                SUM(import_status = 'skipped') AS skipped
            FROM `{$prefix}mtb_osc_product`"
        );

        $specialsStats = Db::getInstance()->getRow(
            "SELECT
                COUNT(*) AS total,
                SUM(import_status = 'pending') AS pending,
                SUM(import_status = 'imported') AS imported,
                SUM(import_status = 'skipped') AS skipped
            FROM `{$prefix}mtb_osc_specials`"
        );

        return [
            'products' => is_array($productStats) ? $productStats : [],
            'specials' => is_array($specialsStats) ? $specialsStats : [],
        ];
    }

    /**
     * @return string
     */
    protected function renderSettingsForm()
    {
        if (Tools::isSubmit('submitOscSettings')) {
            $this->processSaveSettings();
        }

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->module = $this->module;
        $helper->default_form_language = $this->context->language->id;
        $helper->submit_action = 'submitOscSettings';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminMtbOscImport');
        $helper->token = $this->token;

        $helper->tpl_vars = [
            'fields_value' => [
                MtbModelImporter::CONFIG_OSC_BASE_IMAGE_URL => Configuration::get(
                    MtbModelImporter::CONFIG_OSC_BASE_IMAGE_URL
                ),
                MtbModelImporter::CONFIG_OSC_FALLBACK_CATEGORY => (int) Configuration::get(
                    MtbModelImporter::CONFIG_OSC_FALLBACK_CATEGORY
                ),
                MtbModelImporter::CONFIG_OSC_BATCH_SIZE => (int) Configuration::get(
                    MtbModelImporter::CONFIG_OSC_BATCH_SIZE
                ),
                MtbModelImporter::CONFIG_OSC_TAX_MAP_23 => Configuration::get(
                    MtbModelImporter::CONFIG_OSC_TAX_MAP_23
                ),
                MtbModelImporter::CONFIG_OSC_TAX_MAP_5 => Configuration::get(
                    MtbModelImporter::CONFIG_OSC_TAX_MAP_5
                ),
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        $formFields = [
            'form' => [
                'legend' => [
                    'title' => $this->module->l('OSC Import Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->module->l('Base Image URL'),
                        'name' => MtbModelImporter::CONFIG_OSC_BASE_IMAGE_URL,
                        'size' => 80,
                        'desc' => $this->module->l(
                            'Root URL of the old osCommerce shop image directory '
                            . '(e.g. https://oldshop.example.com/images/products). '
                            . 'Image filenames from the CSV will be appended to this URL.'
                        ),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->module->l('Fallback Category ID'),
                        'name' => MtbModelImporter::CONFIG_OSC_FALLBACK_CATEGORY,
                        'size' => 10,
                        'desc' => $this->module->l(
                            'PS category ID used when no osC category is mapped. '
                            . 'Set this category to active=0 (hidden) in PrestaShop. '
                            . 'Products placed here will have visibility=search (searchable but not in nav).'
                        ),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->module->l('Batch Size'),
                        'name' => MtbModelImporter::CONFIG_OSC_BATCH_SIZE,
                        'size' => 5,
                        'desc' => $this->module->l('Number of products imported per batch run (default: 50).'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->module->l('Tax Class IDs → 23 %'),
                        'name' => MtbModelImporter::CONFIG_OSC_TAX_MAP_23,
                        'size' => 30,
                        'desc' => $this->module->l(
                            'Comma-separated list of osCommerce products_tax_class_id values '
                            . 'that map to the 23 % PS tax rule group.'
                        ),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->module->l('Tax Class IDs → 5 %'),
                        'name' => MtbModelImporter::CONFIG_OSC_TAX_MAP_5,
                        'size' => 30,
                        'desc' => $this->module->l(
                            'Comma-separated list of osCommerce products_tax_class_id values '
                            . 'that map to the 5 % PS tax rule group.'
                        ),
                    ],
                ],
                'submit' => [
                    'title' => $this->module->l('Save'),
                ],
            ],
        ];

        return $helper->generateForm([$formFields]);
    }
}
