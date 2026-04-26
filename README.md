# Whisker Example Plugin

Example WordPress plugin demonstrating how to integrate Whisker licensing.

## What this shows

- License activation
- License validation
- Feature gating
- Background validation

## Installation (under 5 minutes)

1. Download or clone this repository.
2. Ensure the plugin folder is named `whisker-example-plugin`.
3. Create a zip from that folder.
4. In WordPress Admin, go to `Plugins -> Add New -> Upload Plugin`.
5. Upload the zip and click `Install Now`.
6. Click `Activate Plugin`.

## Setup (critical)

Use your real Whisker `product_key` before testing activation.

1. Open `whisker-example-plugin.php`.
2. Find:

```php
define( 'WHISKER_PRODUCT_KEY', 'pk_whisker_xxxxx' );
```

3. Replace with your real key from the Whisker dashboard:

```php
define( 'WHISKER_PRODUCT_KEY', 'pk_whisker_xxx' );
```

Also confirm API base is correct:

```php
define( 'WHISKER_API_BASE', 'https://usewhisker.com/api/license' );
```

## Activate license

1. Go to `Settings -> Whisker License`.
2. Enter a license key (example: `WHISKER-TEST-1234`).
3. Click `Activate License`.

Expected result:

- Status shows **Active**
- No error notices

## How it works

The plugin flow is intentionally simple:

1. `activate()` sends your `product_key`, `license_key`, and `site_url` to `/api/license/activate`.
2. On success, the plugin stores license state in WordPress options.
3. `validate()` checks current license status with `/api/license/validate`.
4. `is_active()` is used to gate premium behavior safely.
5. A background cron job runs validation twice daily.

## Example feature gating

```php
if ( ! $license->is_active() ) {
	return;
}
```

## Troubleshooting

- `invalid_product`  
  Your `WHISKER_PRODUCT_KEY` is wrong. Update it in `whisker-example-plugin.php`.

- `product_mismatch`  
  The license key belongs to a different product than your configured `product_key`.

- `network_error`  
  WordPress cannot reach the Whisker API. Check outgoing HTTP access, SSL, and firewall rules.

- `localhost`  
  Localhost URLs are normalized automatically by the plugin to reduce activation duplicates.

## Platform links

- Dashboard: [https://usewhisker.com/dashboard](https://usewhisker.com/dashboard)
- Docs: [https://usewhisker.com/docs](https://usewhisker.com/docs)
- API Reference: [https://usewhisker.com/docs/license-api](https://usewhisker.com/docs/license-api)
