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
 * Downloads and imports product images, skipping duplicates based on content hash.
 */
class MtbImageImportService
{
    /**
     * Download and import images for a given PrestaShop product.
     *
     * Only imports each image once, comparing SHA-256 hashes to detect duplicates.
     *
     * @param int $idProduct PrestaShop product ID.
     * @param array $imageUrls List of image URLs to import.
     * @return array Summary: ['imported' => int, 'skipped' => int, 'failed' => int].
     */
    public function downloadAndImport($idProduct, array $imageUrls)
    {
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        $existingHashes = $this->getExistingHashes((int) $idProduct);

        foreach ($imageUrls as $url) {
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                ++$failed;

                continue;
            }

            $imageData = $this->fetchImageData($url);

            if ($imageData === null) {
                ++$failed;
                MtbModelImporter::log('Failed to download image: ' . $url, 'warning');

                continue;
            }

            $hash = hash('sha256', $imageData);

            if (in_array($hash, $existingHashes, true)) {
                ++$skipped;

                continue;
            }

            $tmpFile = $this->saveTmpFile($imageData);

            if ($tmpFile === null) {
                ++$failed;

                continue;
            }

            $image = new Image();
            $image->id_product = (int) $idProduct;
            $image->position = Image::getHighestPosition((int) $idProduct) + 1;
            $image->cover = empty($existingHashes) && $imported === 0;

            if ($image->add()) {
                if (!$this->copyImageToProductFolder($tmpFile, (int) $idProduct, (int) $image->id)) {
                    $image->delete();
                    ++$failed;
                } else {
                    $existingHashes[] = $hash;
                    ++$imported;
                    MtbModelImporter::log(
                        'Image imported for product ' . $idProduct,
                        'info',
                        ['url' => $url, 'image_id' => (int) $image->id]
                    );
                }
            } else {
                ++$failed;
            }

            @unlink($tmpFile);
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    /**
     * Get SHA-256 hashes of images already associated with the product.
     *
     * @param int $idProduct
     * @return array
     */
    protected function getExistingHashes($idProduct)
    {
        $images = Image::getImages((int) Configuration::get('PS_LANG_DEFAULT'), (int) $idProduct);

        if (!is_array($images)) {
            return [];
        }

        $hashes = [];

        foreach ($images as $imgData) {
            $path = _PS_PROD_IMG_DIR_ . Image::getImgFolderStatic((int) $imgData['id_image'])
                . (int) $imgData['id_image'] . '.jpg';

            if (file_exists($path)) {
                $data = @file_get_contents($path);

                if ($data !== false) {
                    $hashes[] = hash('sha256', $data);
                }
            }
        }

        return $hashes;
    }

    /**
     * Fetch raw image data from a URL via cURL.
     *
     * @param string $url
     * @return string|null
     */
    protected function fetchImageData($url)
    {
        $ch = curl_init($url);

        if ($ch === false) {
            return null;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MTBModelImporter/1.0');

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($data === false || $httpCode !== 200) {
            return null;
        }

        return (string) $data;
    }

    /**
     * Save image data to a temporary file.
     *
     * @param string $data
     * @return string|null Temp file path or null on failure.
     */
    protected function saveTmpFile($data)
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mtbimg_');

        if ($tmpFile === false) {
            return null;
        }

        if (file_put_contents($tmpFile, $data) === false) {
            @unlink($tmpFile);

            return null;
        }

        return $tmpFile;
    }

    /**
     * Copy a temporary image file to the PrestaShop product image folder.
     *
     * @param string $tmpFile
     * @param int $idProduct
     * @param int $idImage
     * @return bool
     */
    protected function copyImageToProductFolder($tmpFile, $idProduct, $idImage)
    {
        $imgFolder = _PS_PROD_IMG_DIR_ . Image::getImgFolderStatic($idImage);

        if (!is_dir($imgFolder) && !mkdir($imgFolder, 0755, true)) {
            return false;
        }

        $destination = $imgFolder . $idImage . '.jpg';

        return ImageManager::resize($tmpFile, $destination);
    }
}
