# MTB Model Importer – Documentation

## Overview

**mtbmodelimporter** is a PrestaShop 8.2+ module with two main functions:

1. **MTB Model catalog import** – import railway model products from the public MTB Model catalog
   and dealer price lists (manual approval workflow).
2. **osCommerce migration** – import products, specials, categories and manufacturers from an
   osCommerce shop export (CSV-based, no direct DB access to the old shop).

---

## Prerequisites

- PrestaShop **8.2.0 – 8.9.99**
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

> **Warning:** Uninstalling the module will drop all module database tables including the OSC
> staging and mapping tables. Back up your data before uninstalling.

---

## osCommerce Migration

### Overview

The OSC import subsystem migrates products from an osCommerce shop to PrestaShop **without** a
direct database connection to the old shop.  All data is provided via CSV exports.

**Key rules enforced:**
- Only **active products** (`products_status = 1`) are staged.
- Only **active specials** (`status = 1`) are staged.
- **Prices are stored without VAT** (`products_price` is the osC net price).
- `products_id` from osCommerce is **never** used as the PS `id_product`; the old ID is stored
  only in the `mtb_osc_product_map` mapping table.
- Products are **not duplicated**: if `osc_products_id` already exists in the map table, the
  record is skipped.
- A product can be placed in **multiple PS categories** (resolved from pipe-separated
  `categories_ids` in the CSV).
- Each osC category is mapped **individually** in the Category Map screen.
- Categories with **ignore_binding = 1** are excluded from the mapping.
- Products whose all osC categories are unmapped or ignored are placed in the **fallback category**.
- The fallback category should be set to `active = 0` (hidden) in PrestaShop;
  products placed there receive `visibility = 'search'` (searchable, not in nav).
- **Stock is not imported** (quantity = 0).
- `availability`, `is_new`, `is_optimum` are staged in `mtb_osc_product` but not pushed to PS
  (reserved for future development).
- osC **specials** are imported as PS **SpecificPrice** with `reduction_type = amount`.
- **Product and category redirects** are recorded in `mtb_osc_redirect` after import.

---

### CSV Export Format

#### products.csv

| Column | Required | Description |
|---|---|---|
| `products_id` | ✓ | osCommerce products_id |
| `products_name` | ✓ | Product name |
| `products_price` | ✓ | Net price (without VAT) |
| `products_status` | ✓ | 1 = active, 0 = inactive |
| `products_model` | | SKU / model number |
| `manufacturers_name` | | Manufacturer name |
| `products_description` | | HTML description |
| `products_tax_class_id` | | osC tax class ID |
| `products_image` | | Main image filename |
| `products_date_available` | | Date available |
| `categories_ids` | | Pipe-separated osC category IDs (e.g. `12\|34`) |
| `availability` | | Availability status (stored only) |
| `is_new` | | New flag 0/1 (stored only) |
| `is_optimum` | | Optimum flag 0/1 (stored only) |
| `subimage1` – `subimage6` | | Additional image filenames |

#### specials.csv

| Column | Required | Description |
|---|---|---|
| `specials_id` | ✓ | osCommerce specials_id |
| `products_id` | ✓ | osCommerce products_id |
| `specials_new_products_price` | ✓ | Discounted net price |
| `status` | ✓ | 1 = active |
| `specials_date_added` | | Date added |
| `expires_date` | | Expiry date |

#### categories.csv

| Column | Required | Description |
|---|---|---|
| `categories_id` | ✓ | osCommerce categories_id |
| `categories_name` | ✓ | Category name |
| `parent_id` | | Parent category ID |

---

### OSC Migration Workflow

1. **Configure OSC settings** (`OSC Import → Settings`):
   - Set *Base Image URL* (root URL of old shop image directory).
   - Set *Fallback Category ID* (hidden PS category for unmapped products).
   - Set *Batch Size* (products per batch run, default 50).
   - Set *Tax Class IDs → 23 %* and *Tax Class IDs → 5 %* (comma-separated osC tax_class_id values).

2. **Upload Categories CSV** (`OSC Import → Upload Categories CSV`):
   - Populates the Category Map table with osC category names.

3. **Map categories** (`OSC Import → Category Map`):
   - For each osC category, select the target PS category.
   - Or check *Ignore Binding* to skip that category.

4. **Upload Products CSV** (`OSC Import → Upload Products CSV`):
   - Stages active products into `mtb_osc_product`.
   - Also registers discovered manufacturer names and category IDs.

5. **Map manufacturers** (`OSC Import → Brand Map`):
   - Select an existing PS manufacturer for each osC manufacturer name.
   - Leave unmapped to auto-create a new PS manufacturer from the name.

6. **Run Batch Import** (`OSC Import → Run Batch Import`):
   - Processes the next *Batch Size* pending products.
   - Repeat until all products show `imported` or `skipped` status.

7. **Upload Specials CSV** (`OSC Import → Upload Specials CSV`):
   - Stages active specials.

8. **Run Specials Batch** (`OSC Import → Run Specials Batch`):
   - Imports staged specials as PS SpecificPrice records.

9. **View Redirects** (`OSC Import → Redirects`):
   - Review product and category redirect records for use in `.htaccess`.

---

### OSC Database Tables

| Table | Description |
|---|---|
| `mtb_osc_product` | Staged osC products (CSV import staging) |
| `mtb_osc_specials` | Staged osC specials (CSV import staging) |
| `mtb_osc_category_map` | osC categories_id → PS id_category mapping |
| `mtb_osc_manufacturer_map` | osC manufacturers_name → PS id_manufacturer mapping |
| `mtb_osc_product_map` | osc_products_id → PS id_product mapping (old IDs stored here only) |
| `mtb_osc_redirect` | Product and category redirect records (osc URL → PS URL) |

---

### OSC Services

| Class | File | Description |
|---|---|---|
| `MtbOscCsvReader` | `src/Service/OscCsvReader.php` | Reads/validates osC CSV exports |
| `MtbOscStagingImporter` | `src/Service/OscStagingImporter.php` | Loads CSV into staging tables |
| `MtbOscManufacturerMapper` | `src/Service/OscManufacturerMapper.php` | Matches/creates PS manufacturers by name |
| `MtbOscCategoryMapper` | `src/Service/OscCategoryMapper.php` | Resolves osC category IDs to PS category IDs |
| `MtbOscProductImporter` | `src/Service/OscProductImporter.php` | Imports staged products to PS (batch) |
| `MtbOscSpecialsImporter` | `src/Service/OscSpecialsImporter.php` | Imports specials as SpecificPrice (batch) |
| `MtbOscRedirectManager` | `src/Service/OscRedirectManager.php` | Manages redirect records |

---

## License

Academic Free License (AFL 3.0) – see `LICENSE` file.
