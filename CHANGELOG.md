# Changelog

## 1.0.0-beta.2 — unreleased candidate

- Rename to `devneok/neokpay-laravel` and require `devneok/neokpay-php ^1.0.0-beta.2`.
- Update Composer version diagnostics and isolated CI metadata; PHP namespaces are unchanged.
- Supersedes the beta.1 pre-registry plan because its Composer vendor is unavailable. The SDK's existing beta.1 tag remains untouched and must not be submitted to Packagist.

## 1.0.0-beta.1 — superseded pre-registry candidate (not tagged)

- Thin integration with the NEOK Pay PHP SDK, originally requiring ^1.0.0-beta.1.
- Laravel auto-discovery, publishable configuration and optional facade sharing the injected SDK client.
- Automatic Guzzle PSR-18 and PSR-17 factory bindings.
- Repeat-safe installer and local doctor diagnostics using Composer package versions.
- Optional raw-body verified webhook route and PaymentSucceeded/UnknownWebhookReceived events.
- Stateless installation without package migrations or tables.
- Certified Laravel 11.56.1 and 12.69.2 on PHP 8.3.33/8.4.25, and Laravel 13.32.0 on PHP 8.4.25.
- 18 Testbench tests / 77 assertions; isolated Composer matrix and fresh-app harnesses.

No payment engine or Stellar logic. No automatic fulfillment or exactly-once guarantee. No tag or publication has occurred.
