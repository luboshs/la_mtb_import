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
 * Scrapes the MTB Model public catalog and detects product changes.
 */
class MtbPublicCatalogScraper
{
    const BASE_URL = 'https://www.mtb-model.com';

    /**
     * Map of scale codes to URL paths.
     *
     * @var array
     */
    protected $scaleUrls = [
        'H0' => '/en/h0-scale',
        'TT' => '/en/tt-scale',
        'N' => '/en/n-scale',
    ];

    /**
     * Sync the public catalog for a given scale.
     *
     * Fetches the catalog page, parses products, stores/updates records,
     * and returns a summary of counts and changes.
     *
     * @param string $scale One of: H0, TT, N.
     * @return array Summary: ['count' => int, 'changed' => int, 'new' => int].
     * @throws Exception When scale is invalid or HTTP request fails.
     */
    public function sync($scale)
    {
        if (!in_array($scale, MtbModelImporter::SCALES, true)) {
            throw new Exception('Invalid scale: ' . $scale);
        }

        $url = self::BASE_URL . ($this->scaleUrls[$scale] ?? '');
        $html = $this->fetchUrl($url);

        if ($html === null) {
            throw new Exception('Failed to fetch catalog page for scale ' . $scale);
        }

        $products = $this->parseHtml($html, $scale, $url);

        return $this->saveProducts($products, $scale);
    }

    /**
     * Fetch a URL via cURL.
     *
     * @param string $url
     * @return string|null
     */
    protected function fetchUrl($url)
    {
        $ch = curl_init($url);

        if ($ch === false) {
            return null;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MTBModelImporter/1.0');

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $httpCode !== 200) {
            return null;
        }

        return (string) $body;
    }

    /**
     * Parse product list from catalog HTML.
     *
     * @param string $html
     * @param string $scale
     * @param string $baseUrl
     * @return array
     */
    protected function parseHtml($html, $scale, $baseUrl)
    {
        $products = [];
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $items = $xpath->query('//div[contains(@class,"product") or contains(@class,"item")]');

        if ($items === false) {
            return $products;
        }

        foreach ($items as $item) {
            $name = '';
            $description = '';
            $url = '';
            $imageUrls = [];

            $nameNode = $xpath->query('.//h2 | .//h3 | .//a[@class="product-name"]', $item);

            if ($nameNode !== false && $nameNode->length > 0) {
                $name = trim((string) $nameNode->item(0)->textContent);
            }

            if (empty($name)) {
                continue;
            }

            $linkNode = $xpath->query('.//a[contains(@href, "/")]', $item);

            if ($linkNode !== false && $linkNode->length > 0) {
                $href = (string) $linkNode->item(0)->getAttribute('href');
                $url = strpos($href, 'http') === 0 ? $href : self::BASE_URL . $href;
            }

            $descNode = $xpath->query('.//p | .//div[contains(@class,"description")]', $item);

            if ($descNode !== false && $descNode->length > 0) {
                $description = trim((string) $descNode->item(0)->textContent);
            }

            $imgNodes = $xpath->query('.//img', $item);

            if ($imgNodes !== false) {
                foreach ($imgNodes as $img) {
                    $src = (string) $img->getAttribute('src');

                    if (!empty($src)) {
                        $imageUrls[] = strpos($src, 'http') === 0 ? $src : self::BASE_URL . $src;
                    }
                }
            }

            $products[] = [
                'scale' => $scale,
                'supplier_raw_name' => $name,
                'description' => $description,
                'source_url' => $url,
                'image_urls' => $imageUrls,
                'hash' => hash('sha256', $name . $description),
            ];
        }

        return $products;
    }

    /**
     * Persist scraped products to the import table.
     *
     * @param array $products
     * @param string $scale
     * @return array Summary ['count' => int, 'changed' => int, 'new' => int].
     */
    protected function saveProducts(array $products, $scale)
    {
        $count = 0;
        $changed = 0;
        $newCount = 0;
        $now = date('Y-m-d H:i:s');

        foreach ($products as $product) {
            $supplierRawName = pSQL((string) ($product['supplier_raw_name'] ?? ''));
            $scaleSafe = pSQL($scale);
            $hash = pSQL((string) ($product['hash'] ?? ''));
            $sourceUrl = pSQL((string) ($product['source_url'] ?? ''));

            $existing = Db::getInstance()->getRow(
                "SELECT `id`, `source_hash`, `status`
                FROM `" . _DB_PREFIX_ . "mtb_import_product`
                WHERE `scale` = '" . $scaleSafe . "'
                    AND `supplier_raw_name` = '" . $supplierRawName . "'"
            );

            if ($existing) {
                if ($existing['source_hash'] !== $hash) {
                    Db::getInstance()->update(
                        MtbModelImporter::TABLE_PRODUCT,
                        [
                            'source_hash' => $hash,
                            'source_url' => $sourceUrl,
                            'status' => MtbModelImporter::STATUS_CHANGED,
                            'updated_at' => $now,
                        ],
                        '`id` = ' . (int) $existing['id']
                    );
                    ++$changed;
                }
            } else {
                $nameGenerator = new MtbProductNameGenerator();
                $generatedName = pSQL(
                    $nameGenerator->generate((string) ($product['supplier_raw_name'] ?? ''))
                );

                Db::getInstance()->insert(
                    MtbModelImporter::TABLE_PRODUCT,
                    [
                        'scale' => $scaleSafe,
                        'supplier_raw_name' => $supplierRawName,
                        'generated_name' => $generatedName,
                        'source_url' => $sourceUrl,
                        'source_hash' => $hash,
                        'status' => MtbModelImporter::STATUS_NEW,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                ++$newCount;
            }

            ++$count;
        }

        return [
            'count' => $count,
            'changed' => $changed,
            'new' => $newCount,
        ];
    }
}
