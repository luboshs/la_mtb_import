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
 * Generates standardized product names from raw MTB supplier names.
 */
class MtbProductNameGenerator
{
    /**
     * Map of railway operator abbreviations to full Czech names.
     *
     * @var array
     */
    protected $operatorMap = [
        'CSD' => 'ČSD',
        'CD' => 'ČD',
        'CFR' => 'CFR',
        'DB' => 'DB',
        'OBB' => 'ÖBB',
        'MAV' => 'MÁV',
        'SZ' => 'SŽ',
        'PKP' => 'PKP',
        'ZSR' => 'ŽSR',
    ];

    /**
     * Map of locomotive series prefixes to vehicle type descriptions.
     *
     * @var array
     */
    protected $typeMap = [
        'M' => 'Motorový vozeň',
        'T' => 'Dieselová lokomotíva',
        'S' => 'Elektrická lokomotíva',
        'E' => 'Elektrická lokomotíva',
        'R' => 'Rýchlik',
        'Reis' => 'Reisezug',
    ];

    /**
     * Generate a standardized product name from a raw supplier name.
     *
     * @param string $rawName
     * @return string
     */
    public function generate($rawName)
    {
        if (empty(trim($rawName))) {
            return '';
        }

        $name = trim((string) $rawName);

        $operator = $this->extractOperator($name);
        $seriesType = $this->extractSeriesType($name);
        $normalizedName = $this->applySymbolSubstitutions($name);

        $parts = [];

        if (!empty($seriesType)) {
            $parts[] = $seriesType;
        }

        $parts[] = $this->normalizeSeriesNumber($normalizedName);

        if (!empty($operator)) {
            $parts[] = $operator;
        }

        $result = implode(', ', array_filter($parts));

        if (empty($result)) {
            return $name;
        }

        return $result;
    }

    /**
     * Apply text substitutions for special characters and conjunctions.
     *
     * @param string $name
     * @return string
     */
    protected function applySymbolSubstitutions($name)
    {
        $name = preg_replace('/\s*\+\s*/', ' s ', $name);

        return (string) $name;
    }

    /**
     * Extract the railway operator abbreviation from the raw name and return the full name.
     *
     * @param string $name
     * @return string
     */
    protected function extractOperator($name)
    {
        foreach ($this->operatorMap as $abbreviation => $full) {
            if (preg_match('/\b' . preg_quote($abbreviation, '/') . '\b/i', $name)) {
                return $full;
            }
        }

        return '';
    }

    /**
     * Determine the vehicle type description from the locomotive series prefix.
     *
     * @param string $name
     * @return string
     */
    protected function extractSeriesType($name)
    {
        if (preg_match('/\b([MSETRmsetr])(\d)/', $name, $m)) {
            $prefix = strtoupper($m[1]);

            if (isset($this->typeMap[$prefix])) {
                return $this->typeMap[$prefix];
            }
        }

        return '';
    }

    /**
     * Normalize the series number (e.g., T466 2364 → T466.2364).
     *
     * @param string $name
     * @return string
     */
    protected function normalizeSeriesNumber($name)
    {
        $name = preg_replace('/\b([A-Z]\d+)\s+(\d+)\b/', '$1.$2', (string) $name);

        return (string) $name;
    }
}
