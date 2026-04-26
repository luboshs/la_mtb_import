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
 * Parses dealer copy-paste text and extracts product data.
 */
class MtbDealerPasteParser
{
    const ORDER_STATUS_SUSPENDED = 'suspended';
    const ORDER_STATUS_UNAVAILABLE = 'unavailable';
    const ORDER_STATUS_AVAILABLE = 'available';

    /**
     * Parse a block of dealer text and return an array of product records.
     *
     * Each record contains:
     *   - supplier_raw_name (string)
     *   - scale (string)
     *   - ean (string|null)
     *   - price (float|null)
     *   - category (string)
     *   - note (string)
     *   - order_status (string)
     *   - has_bearings (bool)
     *   - has_integrated_dcc (bool)
     *   - reference (string)
     *
     * @param string $text
     * @return array
     */
    public function parse($text)
    {
        $text = $this->sanitizeInput($text);
        $lines = preg_split('/\r?\n/', $text);

        if (!is_array($lines)) {
            return [];
        }

        $records = [];
        $currentRecord = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                if ($currentRecord !== null) {
                    $records[] = $this->finalizeRecord($currentRecord);
                    $currentRecord = null;
                }

                continue;
            }

            if ($this->isProductLine($line)) {
                if ($currentRecord !== null) {
                    $records[] = $this->finalizeRecord($currentRecord);
                }

                $currentRecord = $this->startRecord($line);
            } elseif ($currentRecord !== null) {
                $currentRecord = $this->enrichRecord($currentRecord, $line);
            }
        }

        if ($currentRecord !== null) {
            $records[] = $this->finalizeRecord($currentRecord);
        }

        return array_values(array_filter($records, function ($r) {
            return !empty($r['supplier_raw_name']);
        }));
    }

    /**
     * Sanitize raw input.
     *
     * @param string $text
     * @return string
     */
    protected function sanitizeInput($text)
    {
        $text = strip_tags((string) $text);

        return mb_substr($text, 0, 50000);
    }

    /**
     * Determine if a line represents a product name/header.
     *
     * @param string $line
     * @return bool
     */
    protected function isProductLine($line)
    {
        if (!preg_match('/^[A-ZÁČŠŽÝÍÉÓÚĎŤŇ][a-zA-Z0-9ÁČŠŽÝÍÉÓÚĎŤŇ\s\.\-\/\(\)]{2,}/u', $line)) {
            return false;
        }

        $propertyPrefixes = [
            '/^EAN:/i',
            '/^\d+\s*€/',
            '/^Objedn[aá]vky/iu',
            '/^nelze\s+objednat/iu',
            '/^s\s+kuli[cč]kov/iu',
            '/^s\s+DCC/iu',
            '/^DCC/iu',
            '/^pozn[aá]mka/iu',
            '/^kat[.\s]/iu',
            '/^ref[.\s:]/i',
        ];

        foreach ($propertyPrefixes as $pattern) {
            if (preg_match($pattern, $line)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a new record from a product line.
     *
     * @param string $line
     * @return array
     */
    protected function startRecord($line)
    {
        return [
            'supplier_raw_name' => $line,
            'scale' => $this->detectScale($line),
            'ean' => null,
            'price' => null,
            'category' => '',
            'note' => '',
            'order_status' => self::ORDER_STATUS_AVAILABLE,
            'has_bearings' => false,
            'has_integrated_dcc' => false,
            'reference' => '',
            '_lines' => [],
        ];
    }

    /**
     * Enrich an existing record with data from a subsequent line.
     *
     * @param array $record
     * @param string $line
     * @return array
     */
    protected function enrichRecord(array $record, $line)
    {
        $record['_lines'][] = $line;

        if (preg_match('/EAN:\s*([0-9]+)/i', $line, $m)) {
            $record['ean'] = $m[1];
        }

        if (preg_match('/(\d[\d\s]*)\s*€/', $line, $m)) {
            $raw = preg_replace('/\s/', '', $m[1]);
            $record['price'] = (float) $raw;
        }

        if (preg_match('/Objedn[aá]vky\s+pozastaveny/iu', $line)) {
            $record['order_status'] = self::ORDER_STATUS_SUSPENDED;
        }

        if (preg_match('/nelze\s+objednat/iu', $line)) {
            $record['order_status'] = self::ORDER_STATUS_UNAVAILABLE;
        }

        if (preg_match('/kuli[cč]kov[ýy]mi\s+lo[žz]isky/iu', $line)) {
            $record['has_bearings'] = true;
        }

        if (preg_match('/DCC\s+dek[oó]d[eé]r/iu', $line)) {
            $record['has_integrated_dcc'] = true;
        }

        if (preg_match('/kat[.\s]+(.+)/iu', $line, $m)) {
            $record['category'] = trim($m[1]);
        }

        if (preg_match('/pozn[aá]mka[:\s]+(.+)/iu', $line, $m)) {
            $record['note'] = trim($m[1]);
        }

        if (preg_match('/ref[.\s:]+([A-Z0-9\-]+)/i', $line, $m)) {
            $record['reference'] = trim($m[1]);
        }

        return $record;
    }

    /**
     * Finalize a record by removing internal keys.
     *
     * @param array $record
     * @return array
     */
    protected function finalizeRecord(array $record)
    {
        unset($record['_lines']);

        return $record;
    }

    /**
     * Detect the scale (H0, TT, N) from the product name.
     *
     * @param string $name
     * @return string
     */
    protected function detectScale($name)
    {
        if (preg_match('/\bH0\b/i', $name)) {
            return 'H0';
        }

        if (preg_match('/\bTT\b/i', $name)) {
            return 'TT';
        }

        if (preg_match('/\bN\b/', $name)) {
            return 'N';
        }

        return 'H0';
    }
}
