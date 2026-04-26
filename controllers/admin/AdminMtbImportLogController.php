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

class AdminMtbImportLogController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = $this->module->l('MTB Import – Log');
    }

    /**
     * @return void
     */
    public function initContent()
    {
        if (Tools::isSubmit('submitClearLog')) {
            $this->processClearLog();
        }

        $prefix = _DB_PREFIX_;
        $level = pSQL((string) Tools::getValue('filter_level', ''));
        $whereClause = '1';
        $allowedLevels = ['info', 'warning', 'error'];

        if (!empty($level) && in_array($level, $allowedLevels, true)) {
            $whereClause .= " AND `level` = '" . $level . "'";
        }

        $logs = Db::getInstance()->executeS(
            "SELECT `id`, `level`, `message`, `context`, `created_at`
            FROM `{$prefix}mtb_import_log`
            WHERE {$whereClause}
            ORDER BY `created_at` DESC
            LIMIT 500"
        );

        $this->context->smarty->assign([
            'logs' => is_array($logs) ? $logs : [],
            'filterLevel' => Tools::getValue('filter_level', ''),
            'allowedLevels' => $allowedLevels,
            'logUrl' => $this->context->link->getAdminLink('AdminMtbImportLog'),
            'token' => $this->token,
        ]);

        $this->content = $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . 'mtbmodelimporter/views/templates/admin/log.tpl'
        );

        parent::initContent();
    }

    /**
     * @return void
     */
    protected function processClearLog()
    {
        $prefix = _DB_PREFIX_;
        $level = pSQL((string) Tools::getValue('clear_level', ''));

        if (!empty($level) && !in_array($level, ['info', 'warning', 'error'], true)) {
            $this->errors[] = $this->module->l('Invalid log level.');

            return;
        }

        $condition = !empty($level) ? "`level` = '" . $level . "'" : '1';

        if (Db::getInstance()->execute(
            "DELETE FROM `{$prefix}mtb_import_log` WHERE {$condition}"
        )) {
            $this->confirmations[] = $this->module->l('Log entries cleared.');
        } else {
            $this->errors[] = $this->module->l('Failed to clear log entries.');
        }
    }
}
