# FFL order data

Automatic FFL saves the dealer selected at checkout in the existing `sales_order.ffl_license` and `sales_order.ffl_dealer_data` columns. This order snapshot is the source for the admin order section, order API fields, and order history comment. No additional database columns or third-party order-attribute module are required.

## Admin order page

The order's Information tab includes an **FFL Dealer Information** section, using Magento's native section and table styles. It shows the FFL license, expiration date, and labeled links to ATF eZ Check and the certificate. The internal Automatic FFL dealer ID remains available through the order API but is omitted from this human-readable section. The section is separate from billing and shipping addresses and only appears on orders with a saved FFL license, including the applicable multishipping child order.

The date uses the admin locale's date format without converting the date to another timezone. If the saved selection has no expiration date, the section says **Unavailable — verify with ATF eZ Check**. An unavailable certificate is also labeled clearly. Older orders show whatever metadata was captured at checkout; viewing the section does not fetch current dealer data or rewrite the order or its comment.

The shared dealer picker receives `license_expiration_date` from the storefront dealer API and sends it to Magento as `expirationDate`. The backend formats the dealer's stored ATF `license_expiration` code as an ISO date, using the record's import date to resolve the decade. This FFL expiration is independent of whether a certificate is on file or active. Magento does not derive a fallback date from the license number or certificate. The backend must include the field in its dealer response for new selections to capture it. Deploying the Magento admin display alone does not fill missing dates on existing orders.

## Order API

The following fields are returned under `extension_attributes` by the authenticated Magento order repository endpoints, including `GET /rest/V1/orders/{id}` and `GET /rest/V1/orders?searchCriteria[...]`. They also appear in order repository save responses.

| Field | Type | Meaning |
| --- | --- | --- |
| `autoffl_license` | string | Full FFL license, preserving hyphens and leading zeros. |
| `autoffl_dealer_id` | integer or null | Automatic FFL dealer ID from the saved selection. |
| `autoffl_expiration_date` | string or null | Actual saved expiration date in `YYYY-MM-DD` format. |
| `autoffl_certificate_url` | string or null | Certificate URL when the snapshot contains a valid dealer UUID. |
| `autoffl_ezcheck_url` | string | ATF eZ Check lookup URL, or its landing page for a legacy license without six components. |

Example portion of an order response:

```json
{
  "extension_attributes": {
    "autoffl_license": "5-75-121-07-6L-10199",
    "autoffl_dealer_id": 921,
    "autoffl_expiration_date": null,
    "autoffl_certificate_url": "https://certificate.automaticffl.com/11111111-2222-4333-8444-555555555555",
    "autoffl_ezcheck_url": "https://fflezcheck.atf.gov/FFLEzCheck/fflSearch?licsRegn=5&licsDis=75&licsSeq=10199"
  }
}
```

Unavailable optional values may be serialized as null or omitted by Magento. Orders without a saved FFL license have absent or null Automatic FFL fields. Older orders with only `ffl_license` still expose the license and eZ Check URL; the other fields remain unavailable. No historical comments are rewritten.

These are read-only projections of the saved checkout selection. Sending these extension attributes in an order save request does not update the stored dealer selection; the save response repopulates them from the order snapshot. Existing Magento and third-party extension attributes are preserved. Raw snapshot internals, such as store configuration and routing state, are not exposed.

## Parseable order comment

New dealer orders receive one internal order history comment with the same field labels, separators, and date representation used by the BigCommerce integration:

```text
FFL#<license>|Expiration:<MM/DD/YYYY>|EZcheck:<url>|Certificate:<url>
```

`Expiration:` has an empty value when the actual date is unavailable. The date is not inferred from the license. `Certificate:` is omitted when there is no valid dealer UUID. Parse the `|`-delimited fields by their labels; split URL fields at the first `:` only. The comment contains no introductory prose or line breaks. It is a separate history entry, preserving other order comments, and is neither customer-visible nor customer-notified. Repeated application does not add duplicate entries.

For example, an unavailable expiration produces:

```text
FFL#5-75-121-07-6L-10199|Expiration:|EZcheck:https://fflezcheck.atf.gov/FFLEzCheck/fflSearch?licsRegn=5&licsDis=75&licsSeq=10199|Certificate:https://certificate.automaticffl.com/11111111-2222-4333-8444-555555555555
```

The shipping address uses Magento's native address formatting: the customer's name, dealer company, and normal delivery/contact fields. Automatic FFL does not append a license, expiration date, dealer ID, certificate URL, or eZ Check link to the address. These details remain in the order-level fields and internal history comment. Multishipping attaches the data only to the child order containing items that require dealer shipping.

## Connecting other apps

Use the named API fields when an ERP, fulfillment app, or export tool supports mapping order extension attributes. When a connector only imports order history or internal notes, use the standardized comment as the fallback. API exposure does not automatically add fields to third-party imports, CSV exports, labels, or packing slips; configure and verify those mappings separately. Keep FFL details out of customer notes and shipping-label messages unless explicitly required by the merchant.

When installing this code, run Magento's normal dependency compilation and cache refresh so the generated order extension classes include the new fields.
