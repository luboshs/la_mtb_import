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

class MtbmodelimporterCronModuleFrontController extends ModuleFrontController
{
    public $ajax = false;
    public $display_column_left = false;
    public $display_column_right = false;
    public $display_header = false;
    public $display_footer = false;

    /**
     * @return void
     */
    public function init()
    {
        parent::init();

        $token = (string) Tools::getValue('token', '');
        $expectedToken = (string) Configuration::get(MtbModelImporter::CONFIG_CRON_TOKEN);

        if (empty($token) || empty($expectedToken) || !hash_equals($expectedToken, $token)) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Forbidden';
            exit;
        }
    }

    /**
     * @return void
     */
    public function initContent()
    {
        $results = [];

        foreach (MtbModelImporter::SCALES as $scale) {
            try {
                $scraper = new MtbPublicCatalogScraper();
                $result = $scraper->sync($scale);
                $results[$scale] = $result;

                MtbModelImporter::log(
                    'Cron sync completed for scale ' . $scale,
                    'info',
                    $result
                );

                if (isset($result['changed']) && (int) $result['changed'] > 0) {
                    $this->sendChangeNotificationEmail($scale, (int) $result['changed']);
                }
            } catch (Exception $e) {
                $results[$scale] = ['error' => $e->getMessage()];
                MtbModelImporter::log(
                    'Cron sync error for scale ' . $scale . ': ' . $e->getMessage(),
                    'error'
                );
            }
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['status' => 'ok', 'results' => $results]);
        exit;
    }

    /**
     * Send an email notification when catalog changes are detected.
     *
     * @param string $scale
     * @param int $changedCount
     * @return void
     */
    protected function sendChangeNotificationEmail($scale, $changedCount)
    {
        $shopEmail = (string) Configuration::get('PS_SHOP_EMAIL');
        $shopName = (string) Configuration::get('PS_SHOP_NAME');

        if (empty($shopEmail)) {
            return;
        }

        Mail::Send(
            (int) Configuration::get('PS_LANG_DEFAULT'),
            'mtb_cron_notification',
            Mail::l('MTB catalog changes detected'),
            [
                '{scale}' => $scale,
                '{count}' => $changedCount,
                '{shop_name}' => $shopName,
            ],
            $shopEmail,
            $shopName,
            null,
            null,
            null,
            null,
            _PS_MODULE_DIR_ . 'mtbmodelimporter/mails/'
        );
    }
}
