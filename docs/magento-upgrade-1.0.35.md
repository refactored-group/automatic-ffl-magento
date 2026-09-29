# Magento upgrade 1.0.35

This release connects the Magento locator to the shared Automatic FFL iframe, reads shared firearm and ammunition policy, saves a dealer snapshot on the quote and order, adds a private order note, and queues placed-order attribution for Magento cron. It also adds scoped Store Hash, Store Secret, sandbox, shipping, and Maps settings. The optional `ffl_type` product attribute supplements existing `required_ffl` and category rules.

## Upgrade order

1. Release the additive backend Magento policy, category, expiration-date, and attribution support.
2. Release the shared iframe with `platform=Magento` and nullable `expirationDate` support.
3. Install this Magento package in a controlled environment, run Magento's normal `setup:upgrade`, dependency compilation, and static-content deployment, then clear the applicable caches. Review declarative-schema changes before applying them.
4. In **Stores → Configuration → Refactored Group → Automatic FFL**, enable only the intended store views. Set each store view's existing AutoFFL Store Hash and Store Secret; use inheritance or explicit website/store overrides as appropriate. The secret stays in Magento server configuration. It is never a Maps key or frontend setting.
5. Confirm Magento cron is running. `autoffl_order_attributions` delivers up to 25 due records per run. If credentials or the original hash/environment change, reporting blocks instead of sending an old order to a new store. After correcting configuration, an operator can requeue one blocked/failed order with `bin/magento autoffl:attribution:retry <sales_order_entity_id>`.

The category browser calls the installed Magento `autoffl/integration/categories` route over HTTPS using the Store Secret. Category IDs and labels are read in the Magento store scope. Existing saved category IDs remain in AutoFFL if this read fails; missing IDs are shown for explicit removal. Checkout reads current product facts from Magento and shared policy from the backend, so category browsing is not part of an order placement request.

The iframe uses `https://static.automaticffl.com` in production or `https://static-stage.automaticffl.com` in sandbox. Magento CSP permits only those frame origins. If using a merchant Google Maps key, allow the matching hosted iframe origin in that key's HTTP referrer restrictions. Otherwise verify the hosted key fallback is configured. The Store Secret must never be added to a browser referrer restriction or URL.

Magento AsyncOrder is outside this release's supported checkout path. The admin notice flags it when enabled. Standard synchronous checkout and native multishipping are the intended paths. The cron sender is separate from AsyncOrder and sends only after confirmed placement.

## Verification before use

- Check one enabled and one disabled store view, two different hashes/secrets/environments and category roots, and a deliberately shared root configuration. Confirm the map, policy, category tree, and cron target resolve to the correct store view.
- Test a firearm, conditional ammunition in a restricted and unrestricted state, ordinary goods, a mixed cart, and a virtual product. Test both ship-non-gun settings and both mixed-ammunition policy settings.
- Complete a standard dealer checkout. Confirm the shipping address, saved snapshot, private order note, order history/REST representation, pending-to-sent attribution transition, and one backend ledger row. Repeat during a simulated attribution outage: checkout should still complete and cron should retry.
- Complete native multishipping with two dealer destinations and a non-FFL destination, including a split-quantity line. Confirm each successful child order gets only its own dealer data. Induce a partial placement failure and verify the failed child remains retryable and only confirmed successful child orders report attribution.
- Check guest and signed-in checkout, saved-cart restoration, payment retry, billing address independence, narrow-screen iframe behavior, browser CSP, and Maps referrer configuration on the installed theme/payment stack.

Local source validation for this change covers PHP/XML/JS syntax, the shared-map build and tests, backend compilation and pure policy tests, Elm compilation, and quote-analysis and iframe-message smoke checks. This repository does not contain Magento `vendor` or an installed Magento database, so it cannot prove `setup:upgrade`, DI compilation, real checkout, payment, CSP, or multishipping persistence on the target installation. Those checks are release gates for the operator.

## Rollback

Keep the additive backend and iframe compatibility available while upgraded Magento clients may still call them. To revert the Magento package, restore the previous code and configuration in a controlled environment while retaining the new nullable quote/order columns and existing order history. Do not run a blind Composer downgrade followed by `setup:upgrade`: Magento declarative schema may reconcile removed declarations and delete data. Rehearse both upgrade and rollback on a local copy with representative orders first.
