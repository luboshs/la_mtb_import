# mtbmodelimporter

**PrestaShop 8.1 module** for importing and managing MTB Model railway products from the public
catalog and dealer price lists.

## Description

`mtbmodelimporter` is a PrestaShop 8.1 module that helps admins prepare and import MTB Model
products (H0, TT, N scales). It scrapes the public MTB Model catalog, parses dealer copy-paste
data, generates standardized product names, and provides a step-by-step admin workflow.
No product is ever imported automatically — the admin reviews and approves every record.

## Prerequisites

- PrestaShop 8.0.0 – 8.9.99
- PHP 8.1+
- cURL + DOM PHP extensions
- MySQL/MariaDB with utf8mb4 support

## Installation

1. Copy the `mtbmodelimporter` folder to `/modules/` in your PrestaShop installation.
2. Go to **Back Office → Modules → Module Manager**.
3. Search for **MTB Model Importer** and click **Install**.
4. Navigate to **Improve → MTB Import** in the back office menu.

## Configuration

Go to **MTB Import → Settings** and configure:

- **OpenAI API Key** – for automatic product translation (optional)
- **OpenAI Model** – default: `gpt-4.1-mini`
- **Enable Automatic Translation** – toggle OpenAI translation
- **Cron Token** – used to secure the cron endpoint

## Usage

### Workflow

1. **Sync Public Catalog** (`MTB Import → Public Catalog → Sync Now`)
2. **Dealer Import** (`MTB Import → Dealer Import` → paste text → Analyze → Save)
3. **Review Suggestions** (`MTB Import → Suggestions` → edit names → Mark Ready)
4. **Import to Shop** (select category + brand → Import to Shop)

### Cron Job

Schedule automatic catalog sync:
```
0 */6 * * * curl -s "https://yourshop.com/module/mtbmodelimporter/cron?token=YOUR_TOKEN" > /dev/null
```

## Example Output

After syncing the public catalog, the admin sees a table of products with their scale, name,
status (new/changed/ready/imported), and source URL. Dealer-pasted products appear with extracted
EAN codes, prices, and status flags (bearings, DCC decoder).

## Testing

```bash
# Install phpunit (dev dependency)
composer require --dev phpunit/phpunit

# Run unit tests
./vendor/bin/phpunit --testsuite Unit

# Run integration tests
./vendor/bin/phpunit --testsuite Integration
```

## License

Academic Free License (AFL 3.0) – see [LICENSE](LICENSE).
