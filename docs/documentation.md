# MTB Model Importer – Documentation

## Overview

**mtbmodelimporter** is a PrestaShop 8.1 module designed to streamline the process of importing
railway model products from the MTB Model public catalog and dealer price lists. The module follows
a manual-approval workflow: no product is ever automatically imported into the shop without explicit
admin confirmation.

---

## Prerequisites

- PrestaShop **8.0.0 – 8.9.99**
- PHP **8.1+**
- cURL extension enabled
- DOM extension enabled (for HTML parsing)
- MySQL/MariaDB with `utf8mb4` charset support

---

## Installation

1. Upload the `mtbmodelimporter` folder to `/modules/` in your PrestaShop installation.
2. Go to **Back Office → Modules → Module Manager**.
3. Find **MTB Model Importer** and click **Install**.
4. After installation, navigate to **Improve → MTB Import** in the back office menu.

---

## Configuration

Navigate to **MTB Import → Settings** to configure:

| Option | Description |
|---|---|
| **OpenAI API Key** | Your OpenAI API key. Required for automatic translation. Stored encrypted, never logged. |
| **OpenAI Model** | The OpenAI model to use (default: `gpt-4.1-mini`). |
| **Enable Automatic Translation** | Toggle automatic translation of product names/descriptions via OpenAI. |
| **Cron Token** | Secure token for the cron job endpoint. Regenerate if compromised. |

---

## Cron Job

The cron job synchronizes the public MTB Model catalog automatically.

**Endpoint:**
```
/module/mtbmodelimporter/cron?token=YOUR_CRON_TOKEN
```

**Recommended schedule** (every 6 hours):
```bash
0 */6 * * * curl -s "https://yourshop.com/module/mtbmodelimporter/cron?token=YOUR_TOKEN" > /dev/null
```

The cron job:
- Scrapes the public MTB Model catalog for all scales (H0, TT, N)
- Detects product changes via SHA-256 content hashing
- Marks changed products with `status = changed`
- Sends an email notification if changes are detected
- **Does not** import products automatically

---

## Workflow

### 1. Sync Public Catalog

Navigate to **MTB Import → Public Catalog** and click **Sync Now**.

Products found on the MTB Model website are stored in the import table with:
- Status: `new` (first time) or `changed` (if content hash differs)

### 2. Dealer Copy-Paste Import

Navigate to **MTB Import → Dealer Import**.

Paste the dealer product list text into the textarea. Click **Analyze** to preview parsed data.
If the results look correct, click **Save** to store them.

The parser extracts:
- Product name
- EAN code
- Price (€)
- Category
- Order status (available / suspended / unavailable)
- Special flags: ball bearings, integrated DCC decoder

### 3. Review Suggestions

Navigate to **MTB Import → Suggestions**.

For each product, you can:
- Edit the **Admin Name** (overrides the auto-generated name)
- Click **Mark Ready** to mark the product as ready for import
- Select a **Category** and **Brand**, then click **Import to Shop**

### 4. Product Import

When you click **Import to Shop**, the module:
1. Creates a new PrestaShop product (inactive by default)
2. Sets the name, description, EAN, price
3. Assigns the selected category and manufacturer
4. Marks the import record as `imported`

The admin can then review and activate the product in the PrestaShop product catalog.

---

## Database Tables

### `ps_mtb_import_product`

Main product import staging table.

| Column | Type | Description |
|---|---|---|
| `id` | INT | Primary key |
| `id_product` | INT | PrestaShop product ID (after import) |
| `scale` | VARCHAR(10) | Model scale: H0, TT, N |
| `supplier_raw_name` | VARCHAR(255) | Original name from catalog/dealer |
| `supplier_reference` | VARCHAR(100) | Supplier product reference |
| `generated_name` | VARCHAR(255) | Auto-generated standardized name |
| `admin_name` | VARCHAR(255) | Admin-overridden name (takes priority) |
| `source_url` | TEXT | URL of the product on MTB Model website |
| `source_hash` | VARCHAR(64) | SHA-256 hash for change detection |
| `dealer_price` | DECIMAL(20,6) | Dealer price |
| `ean_original` | VARCHAR(50) | Original EAN from dealer |
| `ean_normalized` | VARCHAR(50) | Normalized EAN (digits only, no leading zeros) |
| `dealer_category` | VARCHAR(255) | Category from dealer list |
| `order_status` | VARCHAR(50) | available / suspended / unavailable |
| `dealer_note` | TEXT | Notes from dealer list |
| `has_bearings` | TINYINT(1) | Flag: product has ball bearings |
| `has_integrated_dcc` | TINYINT(1) | Flag: product has integrated DCC decoder |
| `status` | ENUM | new, changed, ready, imported |
| `created_at` | DATETIME | Record creation timestamp |
| `updated_at` | DATETIME | Last update timestamp |

### `ps_mtb_import_product_lang`

Multilingual name/description for each import record.

| Column | Type | Description |
|---|---|---|
| `id` | INT | Primary key |
| `id_product_import` | INT | Foreign key to `mtb_import_product.id` |
| `id_lang` | INT | PrestaShop language ID |
| `name` | VARCHAR(255) | Localized product name |
| `description` | TEXT | Localized product description |
| `status` | ENUM | source, auto, edited, approved |

### `ps_mtb_import_log`

Event log for all module operations.

| Column | Type | Description |
|---|---|---|
| `id` | INT | Primary key |
| `level` | VARCHAR(20) | info, warning, error |
| `message` | TEXT | Log message |
| `context` | TEXT | JSON-encoded context data |
| `created_at` | DATETIME | Timestamp |

---

## Services

| Class | Location | Description |
|---|---|---|
| `MtbEanNormalizer` | `src/Helper/EanNormalizer.php` | Normalizes EAN codes |
| `MtbDealerPasteParser` | `src/Service/DealerPasteParser.php` | Parses dealer copy-paste text |
| `MtbProductNameGenerator` | `src/Service/ProductNameGenerator.php` | Generates standardized product names |
| `MtbOpenAiTranslator` | `src/Service/OpenAiTranslator.php` | Translates via OpenAI API |
| `MtbPublicCatalogScraper` | `src/Service/PublicCatalogScraper.php` | Scrapes public MTB Model catalog |
| `MtbProductImportService` | `src/Service/ProductImportService.php` | Imports products into PrestaShop |
| `MtbImageImportService` | `src/Service/ImageImportService.php` | Downloads and imports product images |

---

## Security

- The cron endpoint is protected by a configurable token (compared with `hash_equals` to prevent timing attacks).
- No automatic login to dealer systems; all data is entered by the admin via copy-paste.
- All database inputs are escaped using `pSQL()`, `(int)`, and `bqSQL()`.
- OpenAI API keys are never written to log files.
- All user inputs are validated and sanitized before processing.
- The `.htaccess` file prevents direct access to PHP files.
- Each directory contains an `index.php` to prevent directory listing.

---

## Testing

Run unit tests:
```bash
./vendor/bin/phpunit --testsuite Unit
```

Run integration tests (requires module files only, no live PrestaShop):
```bash
./vendor/bin/phpunit --testsuite Integration
```

---

## Uninstallation

Go to **Back Office → Modules → Module Manager**, find **MTB Model Importer** and click **Uninstall**.

> **Warning:** Uninstalling the module will drop the `mtb_import_product`, `mtb_import_product_lang`,
> and `mtb_import_log` database tables. Back up your data before uninstalling.

---

## License

Academic Free License (AFL 3.0) – see `LICENSE` file.
