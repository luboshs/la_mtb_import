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
 * Maps osCommerce manufacturer names to PrestaShop manufacturer IDs.
 *
 * Matching strategy (in order):
 *  1. Check mtb_osc_manufacturer_map – if a manual/cached mapping exists, return it.
 *  2. Search PrestaShop Manufacturer by exact name.
 *  3. Create a new PrestaShop Manufacturer and cache the mapping.
 *
 * Results are always persisted in mtb_osc_manufacturer_map so subsequent
 * calls for the same name are resolved from cache without hitting Manufacturer.
 */
class MtbOscManufacturerMapper
{
    /**
     * Return the PrestaShop manufacturer ID for the given osCommerce manufacturer name.
     *
     * Creates the manufacturer in PS if it does not already exist.
     * Returns 0 when the name is empty.
     *
     * @param string $name osCommerce manufacturers_name value.
     * @return int PrestaShop id_manufacturer, or 0 if name is empty.
     */
    public function getOrCreate($name)
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 0;
        }

        // 1. Check cached mapping
        $cached = $this->getCachedMapping($name);

        if ($cached !== null) {
            return (int) $cached;
        }

        // 2. Find existing PS manufacturer by name
        $existing = $this->findPsManufacturerByName($name);

        if ($existing > 0) {
            $this->saveMapping($name, $existing);

            return $existing;
        }

        // 3. Create new PS manufacturer
        $manufacturer = new Manufacturer();
        $manufacturer->name = $name;
        $manufacturer->active = 1;

        if (!$manufacturer->add()) {
            MtbModelImporter::log(
                'Failed to create PS manufacturer: ' . $name,
                'warning'
            );

            return 0;
        }

        $this->saveMapping($name, (int) $manufacturer->id);

        MtbModelImporter::log(
            'Created PS manufacturer: ' . $name,
            'info',
            ['id_manufacturer' => (int) $manufacturer->id]
        );

        return (int) $manufacturer->id;
    }

    /**
     * Return the cached ps_id_manufacturer from mtb_osc_manufacturer_map, or null if absent.
     *
     * @param string $name
     * @return int|null
     */
    protected function getCachedMapping($name)
    {
        $row = Db::getInstance()->getRow(
            "SELECT `ps_id_manufacturer`
            FROM `" . _DB_PREFIX_ . "mtb_osc_manufacturer_map`
            WHERE `osc_manufacturers_name` = '" . pSQL($name) . "'"
        );

        if (!$row) {
            return null;
        }

        if ($row['ps_id_manufacturer'] === null) {
            return null;
        }

        return (int) $row['ps_id_manufacturer'];
    }

    /**
     * Search for an existing PS Manufacturer by exact name.
     *
     * @param string $name
     * @return int PrestaShop id_manufacturer, or 0 if not found.
     */
    protected function findPsManufacturerByName($name)
    {
        $row = Db::getInstance()->getRow(
            "SELECT `id_manufacturer`
            FROM `" . _DB_PREFIX_ . "manufacturer`
            WHERE `name` = '" . pSQL($name) . "'"
        );

        if (!$row) {
            return 0;
        }

        return (int) $row['id_manufacturer'];
    }

    /**
     * Persist a name → ps_id_manufacturer mapping.
     *
     * Inserts or updates the row in mtb_osc_manufacturer_map.
     *
     * @param string $name
     * @param int    $idManufacturer
     * @return void
     */
    protected function saveMapping($name, $idManufacturer)
    {
        $prefix = _DB_PREFIX_;

        $existing = Db::getInstance()->getRow(
            "SELECT `id`
            FROM `{$prefix}mtb_osc_manufacturer_map`
            WHERE `osc_manufacturers_name` = '" . pSQL($name) . "'"
        );

        if ($existing) {
            Db::getInstance()->update(
                'mtb_osc_manufacturer_map',
                ['ps_id_manufacturer' => (int) $idManufacturer],
                "`osc_manufacturers_name` = '" . pSQL($name) . "'"
            );
        } else {
            Db::getInstance()->insert(
                'mtb_osc_manufacturer_map',
                [
                    'osc_manufacturers_name' => pSQL($name),
                    'ps_id_manufacturer' => (int) $idManufacturer,
                ]
            );
        }
    }
}
