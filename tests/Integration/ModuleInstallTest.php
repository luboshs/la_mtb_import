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

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration test placeholder for module installation flow.
 *
 * Full integration tests require a running PrestaShop installation.
 * These tests validate the basic structure of the module files.
 */
class ModuleInstallTest extends TestCase
{
    private string $moduleRoot;

    protected function setUp(): void
    {
        $this->moduleRoot = realpath(__DIR__ . '/../../');
    }

    public function testMainModuleFileExists(): void
    {
        $this->assertFileExists($this->moduleRoot . '/mtbmodelimporter.php');
    }

    public function testMainModuleFileDefinesVersionCheck(): void
    {
        $content = file_get_contents($this->moduleRoot . '/mtbmodelimporter.php');
        $this->assertStringContainsString("if (!defined('_PS_VERSION_'))", (string) $content);
    }

    public function testAllServiceFilesExist(): void
    {
        $serviceFiles = [
            'src/Helper/EanNormalizer.php',
            'src/Service/DealerPasteParser.php',
            'src/Service/ProductNameGenerator.php',
            'src/Service/OpenAiTranslator.php',
            'src/Service/PublicCatalogScraper.php',
            'src/Service/ProductImportService.php',
            'src/Service/ImageImportService.php',
        ];

        foreach ($serviceFiles as $file) {
            $this->assertFileExists(
                $this->moduleRoot . '/' . $file,
                "Service file missing: {$file}"
            );
        }
    }

    public function testAllAdminControllersExist(): void
    {
        $controllers = [
            'controllers/admin/AdminMtbImportDashboardController.php',
            'controllers/admin/AdminMtbImportCatalogController.php',
            'controllers/admin/AdminMtbImportDealerController.php',
            'controllers/admin/AdminMtbImportProductsController.php',
            'controllers/admin/AdminMtbImportSettingsController.php',
            'controllers/admin/AdminMtbImportLogController.php',
        ];

        foreach ($controllers as $controller) {
            $this->assertFileExists(
                $this->moduleRoot . '/' . $controller,
                "Controller missing: {$controller}"
            );
        }
    }

    public function testAllTemplatesExist(): void
    {
        $templates = [
            'views/templates/admin/dashboard.tpl',
            'views/templates/admin/catalog.tpl',
            'views/templates/admin/dealer.tpl',
            'views/templates/admin/products.tpl',
            'views/templates/admin/settings.tpl',
            'views/templates/admin/log.tpl',
        ];

        foreach ($templates as $tpl) {
            $this->assertFileExists(
                $this->moduleRoot . '/' . $tpl,
                "Template missing: {$tpl}"
            );
        }
    }

    public function testCronControllerExists(): void
    {
        $this->assertFileExists($this->moduleRoot . '/controllers/front/cron.php');
    }

    public function testAllPhpFilesContainVersionCheck(): void
    {
        $phpFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->moduleRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $excluded = ['tests/', 'vendor/'];
        $excludedFiles = ['index.php'];

        foreach ($phpFiles as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($this->moduleRoot . '/', '', $file->getPathname());
            $isExcluded = false;

            foreach ($excluded as $excl) {
                if (strpos($relativePath, $excl) === 0) {
                    $isExcluded = true;

                    break;
                }
            }

            if (in_array(basename($file->getPathname()), $excludedFiles, true)) {
                continue;
            }

            if ($isExcluded) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $this->assertStringContainsString(
                "if (!defined('_PS_VERSION_'))",
                (string) $content,
                "Missing _PS_VERSION_ check in: {$relativePath}"
            );
        }
    }

    public function testAllPhpFilesContainLicenseHeader(): void
    {
        $phpFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->moduleRoot, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $excluded = ['tests/', 'vendor/'];
        $excludedFiles = ['index.php'];

        foreach ($phpFiles as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($this->moduleRoot . '/', '', $file->getPathname());
            $isExcluded = false;

            foreach ($excluded as $excl) {
                if (strpos($relativePath, $excl) === 0) {
                    $isExcluded = true;

                    break;
                }
            }

            if (in_array(basename($file->getPathname()), $excludedFiles, true)) {
                continue;
            }

            if ($isExcluded) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $this->assertStringContainsString(
                'Academic Free License',
                (string) $content,
                "Missing license header in: {$relativePath}"
            );
        }
    }

    public function testDocumentationExists(): void
    {
        $this->assertFileExists($this->moduleRoot . '/docs/documentation.md');
    }

    public function testGitignoreExists(): void
    {
        $this->assertFileExists($this->moduleRoot . '/.gitignore');
    }

    public function testHtaccessExists(): void
    {
        $this->assertFileExists($this->moduleRoot . '/.htaccess');
    }

    public function testConfigXmlExists(): void
    {
        $this->assertFileExists($this->moduleRoot . '/config.xml');
    }
}
