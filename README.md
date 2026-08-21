# Endereco Address Validation — Backend Extension for Magento 2

Automatically validate shipping addresses on incoming orders using the Endereco API. Orders with problematic addresses are put on hold for validation, keeping your fulfillment clean and reducing failed deliveries.

This extension was developed in collaboration with [Parc Network](#) <!-- add URL when available -->, a Magento agency supporting shops with Magento development and project delivery.

---

## Main Functionalities

- Validates the shipping address of all incoming orders via the Endereco API
- Puts orders with problematic addresses on hold for validation
- Automatically corrects unambiguous addresses (optional)
- Stores original address, API suggestion, and manual correction in the database for auditing and CSV export
- CSV export of validated addresses from the order grid

---

## How It Works

1. A scheduled job (cron) checks new orders in your configured order status.
2. Each shipping address is sent to the Endereco API for validation.
3. Based on the result, the extension either:
   - **Puts the order on hold for validation** (if the address is ambiguous, has address additions, or returns a critical status), or
   - **Automatically corrects the address** (if the result is unambiguous and auto-overwrite is enabled).
4. All results are saved in the database — original address, API suggestion, and any manual correction.

---

## Requirements

- Magento 2.4 or later
- An active Endereco account with a valid API key → [Get one at endereco.de](https://www.endereco.de/magento/)
- Cron jobs enabled in your Magento installation

---

## Installation

> **Production note:** When running `setup:upgrade` on a live shop, add the `--keep-generated` flag:
> ```bash
> php bin/magento setup:upgrade --keep-generated
> ```
> Without this flag, Magento deletes and rebuilds all auto-generated code during the upgrade, which can cause a brief period of downtime for your customers. The flag keeps the existing generated code intact so the upgrade runs without interruption. On development or staging environments this flag is not needed.

### Type 1: Zip file

1. Unzip the zip file in `app/code/Parc`
2. Enable the module:
   ```bash
   php bin/magento module:enable Parc_AddressValidation
   ```
3. Apply database updates:
   ```bash
   php bin/magento setup:upgrade
   ```
4. Flush the cache:
   ```bash
   php bin/magento cache:flush
   ```

### Type 2: Composer

1. Make the module available in a composer repository, for example:
   - private repository `repo.magento.com`
   - public repository `packagist.org`
   - public GitHub repository as VCS
2. Add the composer repository:
   ```bash
   composer config repositories.repo.magento.com composer https://repo.magento.com/
   ```
3. Install the module:
   ```bash
   composer require parc/module-addressvalidation
   ```
4. Enable the module:
   ```bash
   php bin/magento module:enable Parc_AddressValidation
   ```
5. Apply database updates:
   ```bash
   php bin/magento setup:upgrade
   ```
6. Flush the cache:
   ```bash
   php bin/magento cache:flush
   ```

---

## Configuration

Go to **Stores → Configuration → endereco Backend Address Validation → Address Validation**.

| Setting | Description |
|---|---|
| **Enabled** | Turn the module on or off. |
| **API Key** | Your Endereco API key. |
| **Cron Schedule** | How often the validation job runs (default: `*/10 * * * *` = every 10 minutes). Make sure the interval is long enough for one run to finish before the next starts — see [Cron Schedule and Parallel Runs](#cron-schedule-and-parallel-runs). |
| **Order Status** | Which order statuses should be picked up for validation. Select the statuses that fit your shop's order workflow — typically the status where new orders land before fulfillment begins. Common choices are `Pending` or `Processing`. Multiple statuses can be selected. |
| **Validation Hold Status** | The status orders are set to when they need manual review. We recommend creating a dedicated status for this — see [Create a Custom Validation Hold Status](#recommended-create-a-custom-validation-hold-status). |
| **Sharpness** | Which API result codes trigger a hold (A/B/C classification). See [Sharpness — When Orders Are Put on Hold](#sharpness--when-orders-are-put-on-hold) for a full explanation and recommended setup. |
| **Check additional info** | If enabled, orders where the address contains an addition (e.g. apartment number) are always put on hold. |
| **Auto-Overwrite Address** | If enabled, unambiguous API corrections are automatically written back to Magento's native shipping address fields. **When to enable:** If your shipping or fulfillment extension reads Magento's native address fields directly (e.g. street, postcode, city), enable this so minor corrections from the API are applied there too. **When to leave disabled:** If you use a custom shipping connector that reads the validated address from the `parc_addressvalidation` table or the CSV export of this plugin independently, Magento's native fields are irrelevant and overwriting them is not necessary. |
| **CSV Column Mapping** | Configure which fields appear in the CSV export and from which database tables they are pulled. See [CSV Column Mapping](#csv-column-mapping) below for a full explanation. |

---

## Specifications

### Attributes

The extension stores validation data in the `parc_addressvalidation` table. Each order gets one record holding three versions of the shipping address side by side.

**Metadata**

| Column | Description |
|---|---|
| `address_validation_id` | Internal record ID |
| `created_at` | Timestamp when the record was created |
| `order_id` | Magento internal order ID |
| `order_increment_id` | Magento order number (e.g. `000000123`) |
| `edited_by` | Admin user who last edited the address |
| `edited_at` | Timestamp of the last manual edit |

**Original address** (`orig_*`) — the address as entered by the customer at checkout

| Column | Description |
|---|---|
| `orig_zip_code` | Postal code |
| `orig_city` | City |
| `orig_street_full` | Full street including house number |

**API suggestion** (`api_*`) — the correction proposed by the Endereco API

| Column | Description |
|---|---|
| `api_zip_code` | Postal code |
| `api_city` | City |
| `api_street` | Street name |
| `api_house_number` | House number (split from street) |
| `api_additional_information` | Any address additions returned by the API |
| `status_codes` | Status codes returned by the API (used to determine hold/overwrite logic) |

**Manual correction** (`manu_*`) — correction entered by an admin user

| Column | Description |
|---|---|
| `manu_zip_code` | Postal code |
| `manu_city` | City |
| `manu_street` | Street name |
| `manu_house_number` | House number |
| `manu_additional_information` | Any address additions |

**Address priority** when applying or exporting an address: manual correction → API suggestion → original.

---

## Reviewing Orders on Hold

When an order is put on hold due to address validation, you can review and correct it directly in the Magento Admin:

1. Go to **Sales → Orders** and open the order.
2. At the top of the order detail page you will see the **Billing Address** and **Shipping Address** as entered by the customer.
3. Scroll down to the **Validated Shipping Address** section. This shows the address pre-filled with the API suggestion, split into individual fields. Note that the API automatically separates street name and house number — even if the customer entered them combined (e.g. `Balthasar-Neumann-Str. 4a` is split into `Balthasar-Neumann-Str.` and `4a`):
   - **House Number**
   - **Street**
   - **Zip Code**
   - **City**
   - **Additional Info**
4. Review the fields and make corrections if needed.
5. Use one of the three buttons to proceed:

| Button | What it does |
|---|---|
| **Save** | Saves your changes to the validation record without applying them to the order yet. |
| **Save as shipping address** | Saves your changes and immediately applies this address as the shipping address on the order. |
| **Restore orig. shipping address** | Discards any API suggestion or manual correction and restores the original address the customer entered. |

6. Once the address is correct, click **Unhold** at the top of the page to release the order back into processing.

> **Priority when unholding:** Manual correction → API suggestion → Original address

---

## CSV Export of Validated Addresses

1. Go to **Sales → Orders**.
2. Select the orders you want to export using the checkboxes.
3. In the **Actions** dropdown, choose **Export Validated Addresses (CSV)**.
4. The file downloads with the columns configured under **CSV Column Mapping** in the settings.

---

## CSV Column Mapping

The CSV export is fully configurable — you decide which columns appear in the file and what they are named. The mapping is set up in two steps under **Stores → Configuration → endereco Backend Address Validation → Address Validation**.

---

### Step 1: Select Relevant Tables

First, choose which database tables the export should be able to pull data from. Three tables are available:

| Table | Contains |
|---|---|
| `sales_order_grid` | General order data (order number, status, customer name, totals, etc.) |
| `sales_order_address` | The full shipping and billing address as stored by Magento |
| `parc_addressvalidation` | The validated address data stored by this extension |

You can select one or all three. **Save this selection before moving on** — the tables you pick here determine which columns are available in Step 2.

---

### Step 2: Define the Column Mapping

Once the tables are selected, you can build the column mapping. Each row in the mapping table defines one column in the CSV file:

- **Column Header** — the label that appears in the CSV header row (e.g. `Order Number`, `Validated ZIP`). You can name these freely.
- **Data** — the value that goes in that column, chosen from a dropdown with two groups:

**Group 1 — Validated address fields**

These always return the best available address — manual correction takes priority over the API suggestion.

| Dropdown option | What it returns |
|---|---|
| `validated zip code` | Postal code |
| `validated city` | City |
| `validated street` | Street name |
| `validated house number` | House number |
| `validated additional info` | Address additions |

**Group 2 — Direct database columns**

Every column from each table you selected in Step 1 is available here, shown as `tablename.columnname` (e.g. `sales_order_grid.increment_id`). This lets you include any order data alongside the validated address.

---

### Example Mapping

This mapping shows the original shipping address (as captured at validation time) alongside the best validated address — useful for before/after comparison or feeding an external fulfillment system.

| Column Header | Data |
|---|---|
| Order Number | `sales_order_grid.increment_id` |
| Original Street | `parc_addressvalidation.orig_street_full` |
| Original ZIP | `parc_addressvalidation.orig_zip_code` |
| Original City | `parc_addressvalidation.orig_city` |
| Validated Street | `validated street` |
| Validated House Number | `validated house number` |
| Validated ZIP | `validated zip code` |
| Validated City | `validated city` |
| Validated Additional Info | `validated additional info` |

> **Note:** The `orig_*` fields capture the shipping address at the moment the cron first validated the order. The `validated` fields always return the best available address — manual correction takes priority over the API suggestion.

The resulting CSV uses `;` as the column delimiter.

---

## Sharpness — When Orders Are Put on Hold

The **Sharpness** settings control how strictly the extension filters addresses. An order is put on hold for validation if any of the following conditions is true:

1. The API returns a status code that you have marked as critical.
2. The address contains an addition (e.g. apartment number) and **Check additional info** is enabled.
3. The API returns more than one possible address match (this is always active and cannot be disabled).

---

### Configurable Status Codes

The Endereco API returns a set of status codes for every address it checks. You choose which of these codes count as critical — any order where a critical code appears is put on hold for validation.

The available codes are grouped by address component:

| Group | Codes |
|---|---|
| **Overall result** | `address_correct`, `address_not_found`, `address_multiple_variants`, `address_minor_correction`, `address_major_correction` |
| **Postal code** | `postal_code_correct`, `postal_code_needs_correction`, `postal_code_minor_correction`, `postal_code_major_correction` |
| **City** | `locality_correct`, `locality_needs_correction`, `locality_minor_correction`, `locality_major_correction` |
| **Street** | `street_name_correct`, `street_name_needs_correction`, `street_full_correct`, `street_full_needs_correction`, `street_name_minor_correction`, `street_name_major_correction` |
| **House number** | `building_number_correct`, `building_number_needs_correction`, `building_number_is_missing`, `building_number_not_found`, `building_number_minor_correction`, `building_number_major_correction` |
| **Address addition** | `additional_info_correct`, `additional_info_needs_correction` |
| **Country** | `country_code_correct`, `country_code_needs_correction`, `country_code_minor_correction`, `country_code_major_correction` |
| **Region** | `subdivision_code_correct`, `subdivision_code_needs_correction`, `subdivision_code_minor_correction`, `subdivision_code_major_correction` |
| **Special cases** | `address_is_packstation`, `address_is_postoffice` |

---

### Check additional info

When enabled, any address that contains an addition (e.g. apartment number, floor, c/o) is always put on hold for validation — regardless of the status codes returned by the API.

---

### Recommended Setup

A good starting point that catches serious problems while letting minor corrections through automatically:

**Mark as critical (put on hold for validation):**
- `address_not_found`
- `address_multiple_variants`
- `address_major_correction`
- `building_number_is_missing`
- `building_number_not_found`

**Leave uncritical (auto-correct if Auto-Overwrite is enabled):**
- `address_minor_correction`
- `postal_code_minor_correction`
- `street_name_minor_correction`
- `building_number_minor_correction`

This way, clearly wrong or undeliverable addresses are flagged for manual review, while small typos or formatting differences are corrected automatically without any admin effort.

---

## Recommended: Create a Custom Validation Hold Status

By default you could use Magento's standard `holded` status as the Validation Hold Status. However, we recommend creating a dedicated status (e.g. `Address Validation Pending`) so that orders held for address issues are clearly separated from other holds. This makes it much easier to filter and export only the orders that need address review.

### Step 1: Create the new status

1. Go to **Stores → Settings → Order Status**.
2. Click **Create New Status** and fill in:
   - **Status Code** — internal identifier, lowercase with underscores, e.g. `address_validation_pending`. This cannot be changed after saving.
   - **Status Label** — the label shown in the admin, e.g. `Address Validation Pending`.
3. Click **Save Status**.

### Step 2: Assign the status to the "On Hold" state

1. Click **Assign Status to State**.
2. Set:
   - **Order Status** — select your new status.
   - **Order State** — select **On Hold** (`holded`). This is required because the module internally sets the order state to `holded` when putting an order on hold.
   - **Use Order Status as Default** — leave unchecked unless you want this to replace the default hold status entirely.
3. Click **Save Status Assignment**.

### Step 3: Configure the module

1. Go to **Stores → Configuration → endereco Backend Address Validation → Address Validation**.
2. Set **Validation Hold Status** to your new status (`Address Validation Pending`).

Orders flagged by the extension will now appear under their own status, making it easy to filter the order grid and run targeted CSV exports.

---

## Cron Schedule and Parallel Runs

> **Note:** This is a known limitation that will be addressed in a future release. Until then, follow the guidance below.

The validation cron job processes all unvalidated orders in a single run. If the interval is set too short and a run takes longer than the interval, multiple instances of the job can start in parallel. In that case, two runs can pick up the same order simultaneously, resulting in:

- Duplicate entries in the `parc_addressvalidation` table
- Duplicate order comments
- The order being put on hold twice

**What to do until this is fixed:**

Set the cron interval long enough to ensure one run finishes before the next starts. A safe starting point is every 10 minutes (`*/10 * * * *`). If you process a high volume of orders, increase this further.

To estimate a safe interval, check how long recent runs take in **System → Action Logs** or ask your developer to check the `cron_schedule` table for the `parc_addressvalidation` job.

**Configuration:** Go to **Stores → Configuration → endereco Backend Address Validation → Address Validation → Cron Schedule**.

---

## Troubleshooting

**Orders are not being validated.**
- Check that the module is enabled under **Stores → Configuration → endereco Backend Address Validation → Address Validation**.
- Verify your API key is correct.
- Make sure the orders are in the status configured under "Order Status to Validate".
- Check that cron is running on your server (ask your hosting provider if unsure).

**All orders are being put on hold for validation.**
- Review your **Sharpness** setting — a stricter setting means more orders are flagged.
- Check if "Check additional info" is enabled and whether your customers frequently enter apartment numbers.

**The corrected address is not applied after unholding.**
- Make sure you saved the address correction before unholding the order.

---

## Support

For questions about the Endereco API or your account: [support@endereco.de](mailto:support@endereco.de)

---

## Contributing

Want to fix a bug or add a feature? See [CONTRIBUTING.md](CONTRIBUTING.md) for branch
naming, commit message format, and the QA/test tooling (`composer run qa`, `composer run
serve` for a local Docker playground).
