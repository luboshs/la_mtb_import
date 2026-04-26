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

/**
 * Bootstrap file for unit tests.
 *
 * Defines the _PS_VERSION_ constant so that module files can be loaded
 * outside of a running PrestaShop installation.
 */

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.1.2');
}

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}

if (!defined('_PS_MODULE_DIR_')) {
    define('_PS_MODULE_DIR_', __DIR__ . '/../');
}
