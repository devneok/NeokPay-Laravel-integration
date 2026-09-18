# NEOK Pay for Laravel

A thin, stateless Laravel wrapper around `devneok/neokpay-php`. PHP ^8.3; Laravel 11–13 (Laravel 13 itself requires PHP 8.3+; see the tested matrix in the certification report). No payment-rail configuration, package tables, or migrations.

**Controlled beta candidate: 1.0.0-beta.2. Not tagged or published yet.** See [BETA-PLAN.md](BETA-PLAN.md) before making any payment. PHP 8.3 + Laravel 13 was not part of the certified matrix.

## Installation

The SDK beta.2 is published; this Laravel wrapper is not yet tagged or published. The following is the intended public workflow once the wrapper is available; controlled testers use the local setup below.

```bash
composer require "devneok/neokpay-laravel:1.0.0-beta.2" "devneok/neokpay-php:1.0.0-beta.2"
php artisan neokpay:install
```

The provider auto-discovers and supplies the HTTP client automatically. No manual provider or PSR bindings are needed. The installer publishes `config/neokpay.php`, preserves existing config on subsequent runs, and never edits `.env`.

Specify both packages during beta: a root project's stable policy does not automatically allow the SDK's transitive beta, even with `@beta` on the wrapper alone. This was verified using the published SDK and simulated wrapper beta metadata. No global `minimum-stability` change is necessary. For opt-in compatible beta upgrades, require both with `^1.0.0-beta.2@beta`. The wrapper is not yet on Packagist.

Add server-issued credentials to your application's `.env`:

```dotenv
NEOKPAY_API_KEY=
NEOKPAY_WEBHOOK_SECRET=
NEOKPAY_BASE_URL=https://pay.neok.me/api/v1
```

Set `APP_URL` to your publicly reachable HTTPS merchant URL, then:

```bash
php artisan config:clear
php artisan neokpay:doctor
```

Doctor returns a nonzero exit code for missing/invalid local configuration. It shows installed development versions honestly and never prints credential values. PASS means local configuration is ready, not that live API authentication was tested. There is no safe public health/auth endpoint.

## Create a hosted checkout

Add this example to `routes/web.php`. Replace the fixed example order with an authenticated, authorized order owned by the current customer. Calculate its price server-side; never trust a browser amount. POST through a normal CSRF-protected Laravel form.

```php
use Illuminate\Support\Facades\Route;
use Neok\Pay\Client;
use Neok\Pay\DTO\CreateCheckoutRequest;

Route::view('/payment/success', 'welcome')->name('payment.success');
Route::view('/payment/cancel', 'welcome')->name('payment.cancel');

Route::post('/example-checkout', function (Client $neokPay) {
    $checkout = $neokPay->checkouts()->create(
        new CreateCheckoutRequest(
            reference: 'ORDER-1001',
            amount: '25.00',
            currency: 'USD',
            details: 'Example order',
            webhookUrl: route('neokpay.webhook'),
            successUrl: route('payment.success'),
            cancelUrl: route('payment.cancel'),
            customerName: 'Ada Lovelace',
            customerEmail: 'ada@example.test',
        ),
        idempotencyKey: 'order-1001',
    );

    // Persist $checkout->payment->id alongside your own order.
    return redirect()->away($checkout->checkoutUrl);
});
```

Use a distinct stable key per real order, e.g. `'order-'.$order->id`. Retry the same order with the same key and unchanged input. Changed input with the same key raises `ConflictException`. Keep money as decimal strings.

The optional facade is identical to dependency injection:

```php
use Neok\Pay\Laravel\Facades\NeokPay;

$checkout = NeokPay::checkouts()->create($request, idempotencyKey: 'order-1001');
```

## Confirm payment

**SUCCESS REDIRECT IS NOT PAYMENT PROOF.**

Success/cancel pages are informational. Fulfill only after a verified signed webhook or server-to-server retrieval. Check payment ID, your order reference, expected amount/currency, and paid status before updating your own order.

```php
$payment = app(\Neok\Pay\Client::class)->payments()->retrieve($storedPaymentId);
if ($payment->isPaid()) {
    // Check against your stored order, then fulfill idempotently.
}
```

## Receive webhooks with a normal Laravel listener

The package registers `POST /neokpay/webhook` (named `neokpay.webhook`) outside the web/CSRF middleware group. It does not disable CSRF for other routes. Set the webhook secret issued for your merchant in configuration.

In `App\Providers\AppServiceProvider::boot()`:

```php
\Illuminate\Support\Facades\Event::listen(
    \Neok\Pay\Laravel\Events\PaymentSucceeded::class,
    function (\Neok\Pay\Laravel\Events\PaymentSucceeded $notification): void {
        $eventId = $notification->event->id;
        $payment = $notification->event->payment;
        // $payment->id, ->reference, ->amount, ->currency, ->status->value
        // Atomically record $eventId and update your matching order.
        // Make fulfillment safe against duplicate/concurrent deliveries.
    },
);
```

The SDK verifies the exact raw bytes, HMAC signature and default 300-second timestamp tolerance before dispatch. Valid events return 204; invalid/stale/malformed messages return a generic 400. Verified future types dispatch `UnknownWebhookReceived` with the verified SDK event. No HMAC or payload parsing is needed in listeners.

Timestamp checking does not prevent duplicate deliveries inside the window. Persist processed event IDs with your own order transaction for durable protection. The optional cache deduplication feature is not exactly-once delivery; leave it disabled when reliable processing requires transactional application storage. No package migration is required.

## Errors and troubleshooting

Core SDK exceptions remain available: `AuthenticationException`, `ValidationException` (`errors()` returns field errors), `ConflictException`, `RateLimitException`, and `TransportException`.

Missing commands: verify Composer discovery was not disabled and run `php artisan package:discover`.
Missing configuration: run the installer and doctor. After changing environment values, rebuild/clear the application's config cache.
Rejected webhook: check the configured merchant secret, server clock and unchanged raw request body. Do not log credentials or full payloads to diagnose it.
The transport is supplied automatically; customize the PSR client container binding only if you need custom timeouts.

## Security and sandbox

Keep `.env`, cached configuration and debug output private. Do not log API keys, authorization headers or webhook secrets. Use HTTPS and server-side credentials only. Do not fulfill from a redirect.

Public API-v1 sandbox strategy remains unresolved. `NEOKPAY_BASE_URL` is configurable; use only officially supplied sandbox configuration. No `/sandbox/api/v1` contract is assumed.

Recommendation: use the existing `https://pay.neok.me/api/documentation` route as canonical API documentation. It currently documents the legacy API and must be updated for v1 before being linked as v1 guidance. The repository-only `SERVER-SANDBOX-PLAN.md` is a proposal, not implemented server behavior. Internal phase reports and the sandbox implementation plan are intentionally omitted from distribution archives.

See [SECURITY.md](SECURITY.md), [CHANGELOG.md](CHANGELOG.md) and [RELEASE-CHECKLIST.md](RELEASE-CHECKLIST.md) for beta reporting and release policies.

## Local unpublished packages and testing

For controlled testing only, clone the unpublished wrapper. In a **temporary application's root**, configure its relative path (adjust for the application's location). The SDK comes from Packagist:

```bash
composer config repositories.neok-laravel path ../neokpay-laravel
composer require 'devneok/neokpay-php:1.0.0-beta.2' 'devneok/neokpay-laravel:dev-main'
php artisan neokpay:install
```

No SDK alias or local SDK repository is used. Only the unpublished wrapper uses a local path.

To run Testbench without modifying either public manifest, from this package:

```bash
php tests/matrix.php /tmp/neokpay-matrix-11 11
cd /tmp/neokpay-matrix-11
composer install
composer validate --strict
php vendor/devneok/neokpay-laravel/tests/verify-registry-sdk.php "$PWD"
php vendor/bin/phpunit --do-not-cache-result
php vendor/bin/phpstan analyse --no-progress
```

Use a new directory and major 12 or 13 for other runs. Keep artifacts to inspect failures; delete only your explicitly identified test directory afterward. `tests/fresh-app.php /path/to/test-app` additionally checks a disposable installed application using fake credentials and no live API requests.

Use current patched framework releases in production. Laravel 11 compatibility tests require Composer 2.10+ and apply three narrowly scoped advisory exceptions only in the generated test root (see `tests/laravel11-advisories.php`). There is currently no patched Laravel 11 release for these findings. CI runs an unfiltered `composer audit`, reports the known findings, and fails on unexpected findings. A passing compatibility job does **not** certify Laravel 11 as security-clean; published package metadata and consumer security defaults are unchanged.

The harness defaults to registry mode, with explicit beta permission only in the test root. The provenance check requires SDK beta.2 from the approved public GitHub commit and ZIP distribution. An explicit SDK directory as the third argument remains available only for unreleased SDK development; such runs are not registry-release validation.

CI uses isolated roots for PHP 8.3/8.4 + Laravel 11/12 and PHP 8.4 + Laravel 13, with Larastan 3.12.1 and PHPStan 2.2.14. All jobs install the published SDK from Packagist and verify its exact beta.2 version and source. No sibling SDK checkout, SDK path repository, dev alias, or repository secret is used. Provenance appears in job logs and summaries.
