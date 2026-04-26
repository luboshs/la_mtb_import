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
 * Normalizes EAN barcodes by stripping non-digit characters and leading zeros.
 */
class MtbEanNormalizer
{
    /**
     * Normalize an EAN value.
     *
     * Strips all non-digit characters, then removes leading zeros.
     *
     * @param string $ean
     * @return string
     */
    public function normalize($ean)
    {
        $digitsOnly = preg_replace('/\D/', '', (string) $ean);

        if ($digitsOnly === null || $digitsOnly === '') {
            return '';
        }

        return ltrim($digitsOnly, '0');
    }
}
