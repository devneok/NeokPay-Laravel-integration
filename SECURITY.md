# Security policy

## Reporting

Do not open a public issue containing exploit details or sensitive data. Use this repository's private vulnerability reporting feature when enabled. During invitation-only beta, use the private reporting channel supplied by the beta coordinator; ask the coordinator for that channel if it is missing.

Maintainer release gate: assign a security owner and enable/test the private reporting channel before inviting participants. A public security email or repository URL has not yet been supplied; none is invented here.

Never submit NEOK Pay API keys, Authorization headers, webhook secrets, Stellar secret seeds/private keys, recovery phrases, production payloads or customer personal information. Use synthetic reproductions, package/runtime versions and sanitized logs. Revoke/rotate any accidentally disclosed credential through its owner.

## Supported versions

The planned supported beta line starts at 1.0.0-beta.2; it is not released yet. During beta, install the latest approved beta after review. No stable 1.0 support promise exists yet. Report security findings privately even when using an earlier beta.

## Webhook and payment expectations

Use HTTPS. Verify the exact raw body, signed timestamp and constant-time HMAC; reject stale messages. Timestamp freshness is not durable duplicate prevention: record event IDs and apply order updates atomically/idempotently. Match payment reference, ID, amount, currency and paid status before fulfillment. Browser redirects are never payment proof.

Keep credentials, cached configuration and debug dumps private. Never log full Authorization headers or secrets. Optional cache deduplication is not an exactly-once guarantee. These packages never need any Stellar secret.
