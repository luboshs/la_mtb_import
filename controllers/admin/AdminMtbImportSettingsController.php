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

class AdminMtbImportSettingsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('MTB Import – Settings');
    }

    /**
     * @return void
     */
    public function initContent()
    {
        if (Tools::isSubmit('submitMtbSettings')) {
            $this->processSave();
        }

        $this->context->smarty->assign([
            'settingsForm' => $this->renderSettingsForm(),
            'cronToken' => Configuration::get(MtbModelImporter::CONFIG_CRON_TOKEN),
            'cronUrl' => $this->context->link->getModuleLink(
                'mtbmodelimporter',
                'cron',
                ['token' => Configuration::get(MtbModelImporter::CONFIG_CRON_TOKEN)]
            ),
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'mtbmodelimporter/views/templates/admin/settings.tpl'
        );

        parent::initContent();
    }

    /**
     * @return string
     */
    protected function renderSettingsForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = '';
        $helper->module = $this->module;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = 'id_configuration';
        $helper->submit_action = 'submitMtbSettings';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminMtbImportSettings');
        $helper->token = $this->token;

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        $formFields = [
            'form' => [
                'legend' => [
                    'title' => $this->module->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->module->l('OpenAI API Key'),
                        'name' => MtbModelImporter::CONFIG_OPENAI_API_KEY,
                        'size' => 60,
                        'desc' => $this->module->l('Your OpenAI API key. Stored securely and never logged.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->module->l('OpenAI Model'),
                        'name' => MtbModelImporter::CONFIG_OPENAI_MODEL,
                        'size' => 30,
                        'desc' => $this->module->l('Default: gpt-4.1-mini'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->module->l('Enable Automatic Translation'),
                        'name' => MtbModelImporter::CONFIG_TRANSLATION_ENABLED,
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'enabled', 'value' => 1, 'label' => $this->module->l('Yes')],
                            ['id' => 'disabled', 'value' => 0, 'label' => $this->module->l('No')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->module->l('Cron Token'),
                        'name' => MtbModelImporter::CONFIG_CRON_TOKEN,
                        'size' => 60,
                        'desc' => $this->module->l('Token used to authenticate the cron job.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->module->l('Save'),
                ],
            ],
        ];

        return $helper->generateForm([$formFields]);
    }

    /**
     * @return array
     */
    protected function getConfigValues()
    {
        return [
            MtbModelImporter::CONFIG_OPENAI_API_KEY => Configuration::get(MtbModelImporter::CONFIG_OPENAI_API_KEY),
            MtbModelImporter::CONFIG_OPENAI_MODEL => Configuration::get(MtbModelImporter::CONFIG_OPENAI_MODEL),
            MtbModelImporter::CONFIG_TRANSLATION_ENABLED => (int) Configuration::get(
                MtbModelImporter::CONFIG_TRANSLATION_ENABLED
            ),
            MtbModelImporter::CONFIG_CRON_TOKEN => Configuration::get(MtbModelImporter::CONFIG_CRON_TOKEN),
        ];
    }

    /**
     * @return void
     */
    protected function processSave()
    {
        $apiKey = (string) Tools::getValue(MtbModelImporter::CONFIG_OPENAI_API_KEY, '');
        $model = (string) Tools::getValue(MtbModelImporter::CONFIG_OPENAI_MODEL, 'gpt-4.1-mini');
        $translationEnabled = (int) Tools::getValue(MtbModelImporter::CONFIG_TRANSLATION_ENABLED, 0);
        $cronToken = (string) Tools::getValue(MtbModelImporter::CONFIG_CRON_TOKEN, '');

        if (!empty($model) && !preg_match('/^[a-zA-Z0-9.\-_]+$/', $model)) {
            $this->errors[] = $this->module->l('Invalid model name format.');

            return;
        }

        if (!empty($cronToken) && !preg_match('/^[a-zA-Z0-9]+$/', $cronToken)) {
            $this->errors[] = $this->module->l('Cron token must contain only alphanumeric characters.');

            return;
        }

        Configuration::updateValue(MtbModelImporter::CONFIG_OPENAI_API_KEY, $apiKey);
        Configuration::updateValue(MtbModelImporter::CONFIG_OPENAI_MODEL, $model);
        Configuration::updateValue(MtbModelImporter::CONFIG_TRANSLATION_ENABLED, $translationEnabled);

        if (!empty($cronToken)) {
            Configuration::updateValue(MtbModelImporter::CONFIG_CRON_TOKEN, $cronToken);
        }

        $this->confirmations[] = $this->module->l('Settings saved successfully.');
    }
}
