# Release checklist — 1.0.0-beta.1 candidate

All commit/tag/push/registry/GitHub actions below require a separate explicit authorization. Nothing in this preparation pass executes them.

## Release order (do not reverse)

1. Review and release **neok/neokpay-php 1.0.0-beta.1**.
2. Confirm that exact SDK beta is available from the intended Composer registry and installs from the real tag, not local path metadata.
3. Run the wrapper matrix against the registry SDK beta and review **neok/neokpay-laravel 1.0.0-beta.1**.
4. Release the wrapper beta; verify both packages in a clean consumer application; then invite the controlled cohort.

No static composer.json version is added. Git tags supply release versions. Never move or overwrite a published tag.

## Shared pre-tag gates

- [ ] Real Git repositories/owners and registry visibility approved. The inspected package directories are not Git repositories yet; repository creation/import is a separate authorized action.
- [ ] Security owner assigned and private vulnerability reporting channel enabled/tested.
- [ ] MIT license files and copyright accepted by the owner; manifest license agrees.
- [ ] Review changes, dependency constraints, changelog and beta notices. Remove any "unreleased candidate" wording only when an authorized release actually occurs.
- [ ] Inspect staged diff and release archive for credentials, .env files, vendor trees, local paths and temporary artifacts. Tests/local reports are excluded from distribution archives via .gitattributes.
- [ ] Clean reviewed working tree after the separately authorized commit; no unrelated production files.
- [ ] CI runs on the intended reviewed commit, without production credentials.
- [ ] Beta policy acknowledged: mock-only by default until sandbox exists; supervised live testing requires separate approval.

## SDK gates

- [ ] `composer validate --strict`.
- [ ] `composer install --no-interaction --prefer-dist`.
- [ ] `vendor/bin/phpunit --do-not-cache-result`: 47 tests / 129 assertions baseline.
- [ ] `vendor/bin/phpstan analyse --no-progress`: zero errors.
- [ ] PHP 8.3 and 8.4 hosted workflow green.
- [ ] Review source/security scan, README examples and no framework/rail dependency.
- [ ] After authorization: create immutable `v1.0.0-beta.1` tag on the approved SDK commit; push approved branch/tag; register/update the approved package registry; inspect resulting source/dist metadata.
- [ ] Verify `composer require "neok/neokpay-php:1.0.0-beta.1"` in an empty consumer root.

## Laravel gates

- [ ] SDK beta exists and resolves from the real registry. Public wrapper constraint is `^1.0.0-beta.1`, not dev-main.
- [ ] `composer validate --strict --no-check-lock`; wrapper composer.lock remains absent.
- [ ] 18 Testbench tests / 77 assertions and zero Larastan errors on the certified five-row matrix.
- [ ] SDK beta installs in a fresh Laravel app; install twice, doctor, facade/DI and webhooks pass.
- [ ] Doctor displays actual Composer beta versions, not hardcoded release strings.
- [ ] Both beta dependencies explicitly opted into at the consumer root:
  `composer require "neok/neokpay-laravel:1.0.0-beta.1" "neok/neokpay-php:1.0.0-beta.1"`.
- [ ] No required migration or package table; no global CSRF exemption; no secret logs.
- [ ] After authorization: create immutable `v1.0.0-beta.1` wrapper tag; push approved branch/tag; register/update registry; verify fresh consumer installation.

## Hosted repository and CI setup

Use separate repositories named neokpay-php and neokpay-laravel under the owner-selected account; no owner/domain is guessed. SDK CI is self-contained.

Before SDK registry publication, wrapper CI uses `NEOKPAY_SDK_SOURCE=sibling`, `NEOKPAY_SDK_REPOSITORY=owner/repository` and an approved immutable `NEOKPAY_SDK_REF`. The generated test root simulates SDK beta metadata; it does not tag the repository. There are no absolute server paths or path repositories in public manifests.

For a private SDK repository, configure least-privilege GitHub App/read-only access through Actions secrets only. The workflow deliberately does not invent a token or grant cross-repository access. A normal repository GITHUB_TOKEN may not read a separate private repository. Do not expose checkout credentials to untrusted fork workflows; run pre-publication checks only in an approved trust context.

Once SDK beta is published, set `NEOKPAY_SDK_SOURCE=registry`. The workflow then skips sibling checkout and the harness uses the normal Composer beta dependency. Remove the transitional sibling steps/input in a reviewed follow-up when all release jobs use registry mode. Verify that registry mode actually passes before wrapper tagging; do not claim a simulated path test is registry verification.

## If a beta has a serious issue

1. Pause invitations and affected payment/fulfillment actions. Preserve sanitized evidence.
2. Notify testers through the private coordinator/security channel. Revoke affected API credentials only if exposure or misuse warrants it, through the server's existing credential owner workflow; package rollback does not require blanket key revocation.
3. Pin consumer application lockfiles to a known-safe approved version, or disable the integration if beta.1 is the first release. Reconcile in-flight payments server-to-server before any order decisions. Never roll back payment databases or erase event IDs.
4. Publish an advisory and superseding beta (for example beta.2) only after fixes, tests and explicit authorization. Mark affected versions unsafe through the registry/advisory process where supported. Do not silently rewrite or delete public tags.
5. Re-run affected matrix/integration tests, communicate upgrade instructions and reopen beta only after review.

## Documentation and stable 1.0 gates

Canonical documentation recommendation: extend the existing `https://pay.neok.me/api/documentation` route with the checked-in API-v1 OpenAPI contract. The current legacy page is not yet API-v1 documentation. Confirm the published content before adding a public v1 link.

Sandbox is not required for a mock-only invitation beta. It is required before unattended self-service test payments and stable 1.0 approval. Stable 1.0 additionally needs sandbox isolation tests, real tagged-package installation, hosted CI, completed beta feedback, public API documentation and a separately approved stable release.
