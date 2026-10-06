# Ammunition checkout routing 1.0.37

Category rules remain authoritative, with the old Required FFL flag used only for unmatched products. The ammunition add-on must be active. Magento always applies ammunition destination-state rules; its former "ammo always ships with firearms" switch is hidden in the merchant portal and ignored by the extension, including with older backend snapshots.

## Customer flow

| Physical cart | Checkout |
| --- | --- |
| Ammunition + ordinary products | Ask for a US ammunition destination state on the dedicated routing page when proceeding to checkout, including direct/minicart entry. Unrestricted: standard checkout. Restricted: confirm multiple-address checkout. |
| Ammunition only | Standard checkout. The normal shipping state determines whether the customer must select a dealer. No extra state question before checkout. |
| Firearms + ammunition | Confirm the ammunition destination state on the dedicated routing page on each checkout attempt. Restricted: one dealer in standard checkout. Unrestricted: confirm multishipping for dealer and customer delivery. |
| Firearms + ordinary products, with or without ammunition | Multishipping immediately. Each address's destination determines ammunition routing. |

Virtual products do not create an additional shipping destination. Multiple lines of ammunition remain an ammunition-only cart. An empty ammunition state list requires no extra state prompt. Without an ammunition subscription, ammunition behaves as an ordinary product.

The existing Magento **Ship Non-gun items to FFL** option still groups all physical items at the dealer whenever an item requires one. This also covers ammunition + ordinary products in a restricted state. It does not change an ammunition product's category or state classification.

When the actual shipping state changes in standard checkout, shipping Continue rechecks the quote on the server before proceeding to payment. If separate destinations are needed, a modal explains the split and the native sign-in/registration requirement. The customer explicitly continues to multishipping. If native multishipping is unavailable, the modal explains that separate orders are needed and returns to the cart. Native multishipping's payment-method and quantity limits still apply.

Only ammunition-only checkout reacts to a state change before Continue, so the dealer selector can appear even before a shipping method is available. Dealer addresses never replace the shopper's original routing state. The shopper's billing address is retained when delivery changes to a dealer.

Complete ordinary shipping addresses are kept in the server checkout session for up to two hours, scoped to the store. After the customer signs in and enters multishipping, the address is reused if it belongs to them or saved through Magento's native address repository. It becomes this checkout's initial shipping address without changing their account defaults. If custom required address fields prevent saving it, Magento's new-address form is prefilled so those fields can be completed. No dealer/license metadata is copied into this address.

Shipping-information and order-placement checks reject unresolved ammunition and mixed destinations submitted through standard checkout even if the browser routing is bypassed. Multishipping validates each order's own destination at placement.

## Verification

Local contract checks cover the cart matrix, virtual products, old policy values, state changes, modal confirmation, failure behavior, and address handoff ownership. Installed acceptance still requires guest and registered checkout with actual payment methods and the site's theme. Verify guest sign-in and registration, customer cart merging, dealer selection, changed destinations, and separate order creation on the permitted Magento demo before deploying to merchants.

Run Magento's usual dependency compilation and static-content deployment when installing this package. No schema or data patch is introduced by 1.0.37.
