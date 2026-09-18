# Changelog

## 1.0.0-beta.1 — unreleased candidate

- Thin integration with the NEOK Pay PHP SDK, requiring ^1.0.0-beta.1.
- Laravel auto-discovery, publishable configuration and optional facade sharing the injected SDK client.
- Automatic Guzzle PSR-18 and PSR-17 factory bindings.
- Repeat-safe installer and local doctor diagnostics using Composer package versions.
- Optional raw-body verified webhook route and PaymentSucceeded/UnknownWebhookReceived events.
- Stateless installation without package migrations or tables.
- Certified Laravel 11.56.1 and 12.69.2 on PHP 8.3.33/8.4.25, and Laravel 13.32.0 on PHP 8.4.25.
- 18 Testbench tests / 77 assertions; isolated Composer matrix and fresh-app harnesses.

No payment engine or Stellar logic. No automatic fulfillment or exactly-once guarantee. No tag or publication has occurred.
