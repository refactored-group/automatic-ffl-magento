// Native checkout gating, immediate destination transitions, and address preservation.
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

function observable(value) {
    const subscribers = [];
    function result(next) {
        if (!arguments.length) return value;
        if (value !== next) {
            value = next;
            subscribers.slice().forEach(fn => fn(value));
        }
        return result;
    }
    result.subscribe = (fn, owner) => {
        fn = owner ? fn.bind(owner) : fn;
        subscribers.push(fn);
        return { dispose() { subscribers.splice(subscribers.indexOf(fn), 1); } };
    };
    return result;
}
function deferred() {
    const callbacks = { done: [], fail: [], always: [] };
    const request = {};
    for (const name of Object.keys(callbacks)) {
        request[name] = fn => { callbacks[name].push(fn); return request; };
    }
    request.resolve = value => {
        callbacks.done.forEach(fn => fn(value));
        callbacks.always.forEach(fn => fn());
    };
    request.reject = value => {
        callbacks.fail.forEach(fn => fn(value));
        callbacks.always.forEach(fn => fn());
    };
    return request;
}
const navigations = [];
const modals = [];
const requests = [];
const window = {
    location: { assign: url => navigations.push(url) },
    checkoutConfig: { customerData: { is_ffl: 0 }, autofflRouting: {
        enabled: true, ammoOnly: false, dealerStates: ['CA', 'NY'], selectedState: 'CO',
        stateUrl: '/autoffl/routing/state', formKey: 'fixture'
    } }
};
let routing;
vm.runInNewContext(fs.readFileSync('core/view/frontend/web/js/checkout/routing.js', 'utf8'), {
    window,
    define: (_, factory) => { routing = factory({ ajax: options => {
        const pending = deferred();
        requests.push({ options, pending });
        return pending;
    } }, options => modals.push(options), value => value); }
});
let destination;
vm.runInNewContext(fs.readFileSync('core/view/frontend/web/js/checkout/destination.js', 'utf8'), {
    window, define: (_, factory) => { destination = factory(() => ({ toggleClass() {} }), { observable }, routing); }
});
const home = { getKey: () => 'new-customer-address', getType: () => 'new-customer-address', countryId: 'US', regionCode: 'CO', regionId: 13, firstname: 'Jane', lastname: 'Buyer',
    street: ['10 Home St'], city: 'Home City', postcode: '80202', telephone: '5551231234' };
const split = { route: 'multishipping', requiresDealer: true, requiresLogin: true, url: '/multishipping/checkout' };
assert.ok(routing.isDealer({ customAttributes: [{ attribute_code: 'ffl_license', value: 'fixture' }] }));
routing.continueTo(split);
assert.strictEqual(navigations.length, 0, 'A split must await explicit confirmation.');
assert.match(modals[0].content, /Sign in or create an account/);
modals[0].actions.confirm();
assert.strictEqual(navigations.pop(), split.url);
routing.continueTo({ route: 'unavailable', url: '/checkout/cart' });
assert.strictEqual(modals[1].buttons[1].text, 'Return to cart');
routing.check(window.checkoutConfig.autofflRouting, { ...home, ffl_dealer_data: 'secret', customAttributes: ['secret'] });
assert.strictEqual(requests.pop().options.data.address.includes('secret'), false);

const quote = { shippingAddress: observable(home), billingAddress: observable(null), shippingMethod: observable('old-rate') };
// Native Magento subscribers call getType() for every selected address.
quote.shippingAddress.subscribe(address => assert.ok(address.getType()));
let billingData;
let newBillingData;
let selectedBillingAddress;
let savedShipping;
let rememberedHome;
let dealerIdentity;
let selectedAddress;
let nativeCalls = 0;
let rates;
let createdAddressCount = 0;
const loggedIn = observable(false);
const checkoutData = {
    setShippingAddressFromData: data => { savedShipping = data; },
    getShippingAddressFromData: () => savedShipping,
    getBillingAddressFromData: () => billingData,
    setBillingAddressFromData: data => { billingData = data; },
    setNewCustomerBillingAddress: data => { newBillingData = data; },
    setSelectedBillingAddress: value => { selectedBillingAddress = value; },
    setAutofflCustomerShippingAddress: data => { rememberedHome = data; },
    getAutofflCustomerShippingAddress: () => rememberedHome,
    setNewCustomerShippingAddress() {},
    setFflDealerAddressIdentity: data => { dealerIdentity = JSON.parse(JSON.stringify(data)); },
    setSelectedShippingAddress: value => { selectedAddress = value; },
    setSelectedShippingRate() {}
};
const converter = {
    quoteAddressToFormAddressData: address => ({ firstname: address.firstname, lastname: address.lastname,
        middlename: address.middlename, prefix: address.prefix, suffix: address.suffix, company: address.company,
        city: address.city, postcode: address.postcode, telephone: address.telephone, fax: address.fax,
        vat_id: address.vatId, region: address.region, custom_attributes: address.customAttributes,
        street: address.street, country_id: address.countryId, region_code: address.regionCode, region_id: address.regionId }),
    formAddressDataToQuoteAddress: data => ({ getKey: () => 'new-customer-address', getType: () => 'new-customer-address', firstname: data.firstname, lastname: data.lastname, street: data.street,
        countryId: data.country_id, regionCode: data.region_code, regionId: data.region_id })
};
let definition;
let provider;
const directoryData = observable({ US: { regions: { 12: { code: 'CA', name: 'California' },
    13: { code: 'CO', name: 'Colorado' }, 43: { code: 'NY', name: 'New York' },
    57: { code: 'TX', name: 'Texas' } } } });
vm.runInNewContext(fs.readFileSync('checkout/view/frontend/web/js/view/shipping-routing-mixin.js', 'utf8'), {
    window, JSON,
    define: (_, factory) => { factory(quote, checkoutData, converter, routing, value => value, destination,
        { setShippingRates: value => { rates = value; } }, address => quote.shippingAddress(address),
        { get: () => directoryData },
        { registerProcessor() {} }, data => {
            createdAddressCount++;
            return converter.formAddressDataToQuoteAddress(data);
        }, { isLoggedIn: loggedIn }, { async: () => callback => callback(provider) }, { observable })
        ({ extend: value => { definition = value; return value; } }); }
});
const shipping = Object.create(definition);
shipping._super = () => shipping;
shipping.errorValidationMessage = value => { shipping.error = value; };
shipping.validateShippingInformation = () => true;
shipping.isFormInline = true;
let providerData;
const providerListeners = [];
provider = shipping.source = {
    get: path => path === 'shippingAddress' ? providerData : providerData && providerData[path.split('.')[1]],
    set: (path, data) => {
        if (path === 'shippingAddress') providerData = data;
        else providerData[path.split('.')[1]] = data;
        providerListeners.slice().forEach(listener => listener.callback());
    },
    on: (path, callback, namespace) => providerListeners.push({ callback, namespace }),
    off: namespace => {
        for (let i = providerListeners.length - 1; i >= 0; i--) {
            if (providerListeners[i].namespace === namespace) providerListeners.splice(i, 1);
        }
    }
};
shipping.initialize();
shipping._super = () => { nativeCalls++; };
shipping.setShippingInformation();
assert.strictEqual(nativeCalls, 0, 'Payment must await server routing.');
requests.pop().pending.resolve(split);
assert.strictEqual(nativeCalls, 0, 'A mixed cart cannot submit standard shipping.');
assert.deepStrictEqual(savedShipping.street, home.street);
assert.deepStrictEqual(billingData.street, home.street, 'Home billing survives dealer delivery.');
shipping.setShippingInformation();
requests.pop().pending.resolve({ route: 'standard', requiresDealer: false });
assert.strictEqual(nativeCalls, 1);
shipping.setShippingInformation();
requests.pop().pending.reject({ responseJSON: { error: 'retry routing' } });
assert.strictEqual(nativeCalls, 1);
assert.strictEqual(shipping.error, 'retry routing');
shipping.setShippingInformation();
const stale = requests.pop().pending;
quote.shippingAddress({ ...home, regionCode: 'NY', regionId: 43 });
stale.resolve({ route: 'standard', requiresDealer: false });
assert.strictEqual(nativeCalls, 1, 'An old destination must not advance payment.');
quote.shippingAddress(home);

window.checkoutConfig.autofflRouting.ammoOnly = true;
destination.update('CA', home);
assert.strictEqual(destination.requiresDealer(), true, 'Dealer selection appears before any network response.');
assert.strictEqual(quote.shippingAddress().getType(), 'autoffl-pending', 'Home delivery cannot be submitted as a dealer.');
assert.strictEqual(quote.shippingMethod(), null, 'Old shipping methods are invalidated.');
assert.strictEqual(rates.length, 0);
assert.strictEqual(selectedAddress, null);
assert.deepStrictEqual(rememberedHome.street, home.street);
assert.strictEqual(requests.length, 1, 'No artificial debounce delays the request.');
const first = requests.shift();
shipping.setShippingInformation();
assert.strictEqual(nativeCalls, 1, 'A pending state save blocks payment.');

// Change twice before the first save returns; only the latest destination is saved next.
destination.update('CO');
assert.strictEqual(destination.requiresDealer(), false);
assert.deepStrictEqual(quote.shippingAddress().street, home.street, 'Home form values restore immediately.');
assert.strictEqual(quote.shippingAddress().regionCode, 'CO');
assert.deepStrictEqual(billingData.street, home.street);
destination.update('NY');
assert.strictEqual(destination.requiresDealer(), true);
assert.strictEqual(requests.length, 0, 'Quote writes must be serialized.');
first.pending.resolve({ state: 'CA', route: 'standard', requiresDealer: true });
assert.strictEqual(requests.length, 1);
const latest = requests.shift();
assert.strictEqual(latest.options.data.state, 'NY', 'Intermediate states are coalesced.');
latest.pending.resolve({ state: 'NY', route: 'standard', requiresDealer: true });
assert.strictEqual(destination.saving(), false);
assert.strictEqual(navigations.length, 0, 'Standard state changes never reload checkout.');
shipping.setShippingInformation();
assert.strictEqual(nativeCalls, 1, 'A restricted destination still requires a dealer.');
quote.shippingAddress({ ...home, regionCode: 'CA', dealer_license: 'fixture-license' });
shipping.setShippingInformation();
assert.strictEqual(nativeCalls, 2, 'A dealer must not overwrite the original ammo destination.');

destination.update('CO');
requests.shift().pending.reject({ responseJSON: { error: 'retry destination' } });
assert.strictEqual(destination.requiresDealer(), false);
assert.strictEqual(destination.error(), 'retry destination');
shipping.setShippingInformation();
assert.strictEqual(nativeCalls, 2, 'A failed state save must block checkout.');
destination.update('CO');
requests.shift().pending.resolve({ route: 'standard', requiresDealer: false });
assert.strictEqual(destination.error(), '');
destination.update('');
assert.ok(destination.error(), 'Unknown destinations cannot be treated as unrestricted.');
assert.strictEqual(requests.length, 0);

// Typing in Magento's native form updates checkoutProvider before the quote.
quote.shippingAddress({ getType: () => 'autoffl-pending' });
providerData = { firstname: 'Edited', lastname: 'Customer', street: ['20 Edited St'],
    city: 'Denver', postcode: '80203', telephone: '3035550100', country_id: 'US', region_id: 13,
    custom_attributes: { ffl_license: 'stale-dealer' } };
destination.update('CA');
assert.strictEqual(rememberedHome.firstname, 'Edited');
assert.deepStrictEqual(rememberedHome.street, ['20 Edited St']);
assert.strictEqual(rememberedHome.custom_attributes.ffl_license, undefined, 'Restored home data must not carry stale dealer metadata.');
requests.shift().pending.resolve({ route: 'standard', requiresDealer: true });
providerData.street[0] = '30 Dealer St';
assert.deepStrictEqual(rememberedHome.street, ['20 Edited St'], 'Provider edits cannot mutate the saved home address.');
quote.shippingAddress({ ...home, regionCode: 'CA', dealer_license: 'fixture-license' });
providerData = { firstname: 'Dealer', street: ['30 Dealer St'], country_id: 'US', region_id: 12 };
// Native form synchronization can briefly create an address without FFL metadata.
// It still belongs to dealer delivery and cannot replace the home snapshot.
quote.shippingAddress({ ...home, firstname: 'Dealer', regionCode: 'CA' });
shipping.checkAmmoOnlyDestination(quote.shippingAddress());
assert.strictEqual(destination.state(), 'CA');
assert.strictEqual(requests.length, 0);
destination.update('CO');
assert.strictEqual(providerData.firstname, 'Edited', 'Restoration must use typed home fields, not the stale quote or dealer form.');
assert.deepStrictEqual(providerData.street, ['20 Edited St']);
assert.strictEqual(providerData.region_code, 'CO');
requests.shift().pending.resolve({ route: 'standard', requiresDealer: false });
assert.strictEqual(createdAddressCount, 0, 'Guest home restoration must not add an address-list card.');
loggedIn(true);
shipping.isFormInline = false;
destination.update('CA');
requests.shift().pending.resolve({ route: 'standard', requiresDealer: true });
destination.update('CO');
assert.strictEqual(createdAddressCount, 1, 'Signed-in customers retain native new-address cards.');
requests.shift().pending.resolve({ route: 'standard', requiresDealer: false });
const savedAddress = { ...home, getType: () => 'customer-address', getKey: () => 'saved-home' };
quote.shippingAddress(savedAddress);
destination.update('CA');
requests.shift().pending.resolve({ route: 'standard', requiresDealer: true });
destination.update('CO');
assert.strictEqual(quote.shippingAddress(), savedAddress, 'The saved customer address must be reused.');
assert.strictEqual(createdAddressCount, 1);
requests.shift().pending.resolve({ route: 'standard', requiresDealer: false });

let listDefinition;
vm.runInNewContext(fs.readFileSync('checkout/view/frontend/web/js/view/shipping-address/list-mixin.js', 'utf8'), {
    define: (_, factory) => factory({ pureComputed: (fn, owner) => fn.bind(owner) }, destination, routing, quote,
        { isLoggedIn: loggedIn })({ extend: value => { listDefinition = value; return value; } })
});
const list = Object.create(listDefinition);
list._super = () => list;
const cardAddress = observable(null);
list.elems = () => cardAddress() ? [{ address: cardAddress }] : [];
list.initialize();
assert.strictEqual(list.isCustomerLoggedIn, loggedIn);
loggedIn(false);
assert.strictEqual(list.isAvailableAddress(home), false, 'Guests must not see duplicate home-address cards.');
loggedIn(true);
assert.strictEqual(list.isAvailableAddress(savedAddress), true, 'Saved address cards remain available when signed in.');
loggedIn(false);
destination.setRequirement(true);
const selectedDealer = { ...home, getKey: () => 'new-customer-address',
    customAttributes: [{ attribute_code: 'ffl_license', value: 'fixture-license' }] };
quote.shippingAddress(selectedDealer);
assert.strictEqual(list.isAvailableAddress(selectedDealer), true, 'Guests must still see their chosen dealer.');
assert.strictEqual(list.dealerLicense(), 'fixture-license');
cardAddress(selectedDealer);
quote.shippingAddress({ ...selectedDealer, firstname: 'Changed' });
assert.strictEqual(cardAddress().firstname, 'Changed', 'The selected dealer card updates when recipient names change.');
assert.strictEqual(list.isAvailableAddress(selectedDealer), true, 'Native rate updates must not hide the selected dealer card.');
assert.strictEqual(list.isAvailableAddress({ ...selectedDealer,
    customAttributes: [{ attribute_code: 'ffl_license', value: 'old-license' }] }), false,
    'A previous dealer cannot render as the current selection.');
quote.shippingAddress({ ...home, getKey: () => 'new-customer-address' });
assert.strictEqual(list.dealerLicense(), '');
assert.strictEqual(list.isAvailableAddress(selectedDealer), false, 'A home address cannot keep an old dealer card visible.');
assert.strictEqual(routing.dealerLicense({ extensionAttributes: { ffl_license: 'camel-license' } }), 'camel-license');
assert.strictEqual(routing.isDealer({ extensionAttributes: { ffl_license: 'camel-license' } }), true);
assert.strictEqual(list.isAvailableAddress(home), false);

// A fresh ammo checkout uses one state dropdown even before a destination is
// selected or Magento's directory data has arrived.
shipping.destroy();
destination.setRequirement(false);
destination.state('');
destination.error('');
quote.shippingAddress(converter.formAddressDataToQuoteAddress({ country_id: 'US' }));
providerData = { firstname: 'Fresh', country_id: 'US', region_id: '', region: '', region_code: '' };
const loadedDirectory = directoryData();
directoryData({});
const freshShipping = Object.create(definition);
freshShipping.name = 'fixture-fresh-shipping';
freshShipping._super = () => freshShipping;
freshShipping.isFormInline = true;
freshShipping.source = provider;
freshShipping.errorValidationMessage = value => { freshShipping.error = value; };
freshShipping.initialize();
assert.strictEqual(destination.state(), '');
assert.strictEqual(freshShipping.autofflHideShippingRegion(), true, 'The native state field must be hidden before an ammo destination is selected.');
assert.strictEqual(providerData.region_id, '', 'Hiding the duplicate must not invent a shipping state.');
directoryData(loadedDirectory);
assert.strictEqual(freshShipping.autofflHideShippingRegion(), true, 'Loading directory data must not reveal the duplicate state input.');
assert.strictEqual(providerData.region_id, '');
let freshContinued = false;
freshShipping._super = () => { freshContinued = true; };
freshShipping.validateShippingInformation = () => !!providerData.region_id;
freshShipping.setShippingInformation();
assert.strictEqual(freshContinued, false, 'An empty hidden state must still fail native shipping validation.');
assert.strictEqual(requests.length, 0);
destination.update('CO');
assert.strictEqual(providerData.region_id, '13');
assert.strictEqual(providerData.region_code, 'CO');
assert.strictEqual(freshShipping.autofflHideShippingRegion(), true);
requests.shift().pending.resolve({ route: 'standard', requiresDealer: false });
destination.update('');
assert.strictEqual(freshShipping.autofflHideShippingRegion(), true, 'Returning to the placeholder must keep the duplicate state input hidden.');
freshShipping.setShippingInformation();
assert.strictEqual(freshContinued, false, 'Clearing the ammo destination must block checkout even if native fields retain a previous state.');
assert.strictEqual(freshShipping.error, 'Select a delivery state.');
assert.strictEqual(requests.length, 0);
freshShipping.destroy();
destination.error('');

// A persisted destination controls the inline form on initial load, before
// native delayed rate validation reconstructs the quote from checkoutProvider.
destination.state('TX');
quote.shippingAddress(home);
providerData = { firstname: 'Preserved', country_id: 'US', region_id: 13,
    region_code: 'CO', region: 'Colorado', street: ['10 Home St'] };
const unchangedBilling = JSON.stringify(billingData);
const syncedShipping = Object.create(definition);
syncedShipping.name = 'fixture-shipping';
syncedShipping._super = () => syncedShipping;
syncedShipping.isFormInline = true;
syncedShipping.source = provider;
syncedShipping.errorValidationMessage = () => {};
syncedShipping.initialize();
assert.strictEqual(destination.state(), 'TX', 'Stale initial quote data cannot replace the chosen ammo destination.');
assert.strictEqual(providerData.region_id, '57');
assert.strictEqual(providerData.region_code, 'TX');
assert.strictEqual(providerData.region, 'Texas');
assert.strictEqual(syncedShipping.autofflHideShippingRegion(), true);
assert.strictEqual(providerData.firstname, 'Preserved');
assert.strictEqual(JSON.stringify(billingData), unchangedBilling, 'Shipping state synchronization must not edit billing.');
provider.set('shippingAddress.region_id', '');
assert.strictEqual(providerData.region_id, '57', 'Native field initialization must not clear the chosen required region.');
syncedShipping.validateShippingInformation = () => {
    assert.strictEqual(providerData.region_id, '57', 'Native validation must receive the selected required region.');
    quote.shippingAddress(converter.formAddressDataToQuoteAddress(providerData));
    return true;
};
let continuedWithSyncedState = false;
syncedShipping._super = () => { continuedWithSyncedState = quote.shippingAddress().regionCode === 'TX'; };
syncedShipping.setShippingInformation();
const syncedRequest = requests.shift();
assert.strictEqual(JSON.parse(syncedRequest.options.data.address).regionCode, 'TX');
syncedRequest.pending.resolve({ route: 'standard', requiresDealer: false });
assert.strictEqual(continuedWithSyncedState, true, 'The populated hidden region must allow native shipping continuation.');

provider.set('shippingAddress.country_id', 'CA');
provider.set('shippingAddress.region_id', 94);
assert.strictEqual(syncedShipping.autofflHideShippingRegion(), false, 'Non-US addresses need their native region field.');
assert.strictEqual(providerData.region_id, 94, 'A Canadian province must not be overwritten with a US state.');
provider.set('shippingAddress.country_id', 'US');
assert.strictEqual(providerData.region_id, '57');
directoryData({});
assert.strictEqual(syncedShipping.autofflHideShippingRegion(), true, 'Directory loading must not reveal a second state dropdown.');
directoryData(loadedDirectory);
assert.strictEqual(syncedShipping.autofflHideShippingRegion(), true);

destination.update('CA');
requests.shift().pending.resolve({ route: 'standard', requiresDealer: true });
quote.shippingAddress({ ...home, regionCode: 'TX', regionId: 57, dealer_license: 'fixture-license' });
provider.set('shippingAddress', { country_id: 'US', region_id: 57, region_code: 'TX', region: 'Texas' });
assert.strictEqual(providerData.region_id, 57, 'A Texas dealer must retain Texas when the ammo destination is California.');
assert.strictEqual(syncedShipping.autofflHideShippingRegion(), false);
destination.update('CO');
assert.strictEqual(providerData.region_id, '13');
assert.strictEqual(syncedShipping.autofflHideShippingRegion(), true, 'Home delivery restores synchronized hidden state fields.');
requests.shift().pending.resolve({ route: 'standard', requiresDealer: false });

window.checkoutConfig.autofflRouting.ammoOnly = false;
provider.set('shippingAddress.region_id', 57);
assert.strictEqual(providerData.region_id, 57);
assert.strictEqual(syncedShipping.autofflHideShippingRegion(), false, 'Carts without the ammo-only dropdown keep the native state input.');
// Recipient edits must work for firearms too, without adding another card or
// invalidating the cached rate key or overwriting the customer's billing.
window.checkoutConfig.autofflRouting.enabled = false;
destination.setRequirement(true);
const dealerWithCache = { ...home, getKey: () => 'new-customer-address', getCacheKey: () => 'stable-rate-key',
    customAttributes: [{ attribute_code: 'ffl_license', value: 'fixture-license' }],
    extensionAttributes: { ffl_dealer_data: 'dealer-snapshot' } };
quote.shippingAddress(dealerWithCache);
const beforeRecipientBilling = JSON.stringify(billingData);
const beforeRecipientCreated = createdAddressCount;
provider.set('shippingAddress', { firstname: '  Alex  ', lastname: 'Customer',
    country_id: 'US', custom_attributes: { ffl_license: 'fixture-license' } });
assert.strictEqual(quote.shippingAddress().firstname, 'Alex');
assert.strictEqual(quote.shippingAddress().lastname, 'Customer');
assert.strictEqual(dealerIdentity.firstname.trim(), 'Alex', 'Provenance must follow recipient name edits.');
assert.strictEqual(dealerIdentity.lastname, 'Customer');
assert.strictEqual(cardAddress().firstname, 'Alex');
assert.strictEqual(quote.shippingAddress().getCacheKey(), 'stable-rate-key');
assert.strictEqual(quote.shippingAddress().extensionAttributes.ffl_dealer_data, 'dealer-snapshot');
assert.strictEqual(createdAddressCount, beforeRecipientCreated, 'Name edits must not add addresses to the native list.');
assert.strictEqual(JSON.stringify(billingData), beforeRecipientBilling);
syncedShipping.isFormInline = false;
provider.set('shippingAddress.lastname', '');
let blankContinued = false;
syncedShipping._super = () => { blankContinued = true; };
syncedShipping.setShippingInformation();
assert.strictEqual(blankContinued, false, 'A signed-in dealer selection still requires a last name.');
provider.set('shippingAddress.lastname', 'Customer');
syncedShipping.setShippingInformation();
assert.strictEqual(blankContinued, true);
syncedShipping.destroy();

// Saved customer addresses and checkout data can contain null optional text
// values. Magento's native max_text_length rule reads value.length directly.
// Initialization and later state changes must never push those nulls into UI fields.
window.checkoutConfig.autofflRouting.enabled = true;
window.checkoutConfig.autofflRouting.ammoOnly = true;
loggedIn(true);
destination.setRequirement(false);
destination.state('CO');
destination.error('');
const nullableSavedAddress = { ...savedAddress, company: null, middlename: null, prefix: null,
    suffix: null, fax: null, vatId: null, region: null, street: ['10 Home St', null],
    customAttributes: { delivery_checkbox: false, optional_customer_value: null } };
quote.shippingAddress(nullableSavedAddress);
savedShipping = converter.quoteAddressToFormAddressData(nullableSavedAddress);
savedShipping.street = { 0: '10 Home St', 1: null };
const originalNullableData = JSON.stringify(savedShipping);
const nullableBilling = quote.billingAddress();
const previousCreatedAddresses = createdAddressCount;
const nativeTextFields = ['firstname', 'middlename', 'lastname', 'prefix', 'suffix', 'company',
    'city', 'postcode', 'telephone', 'fax', 'vat_id', 'region'];
function validateNativeTextFields(data) {
    nativeTextFields.forEach(field => {
        const value = data[field];
        if (value !== undefined) assert.ok(value.length <= 255, 'Native max_text_length: ' + field);
    });
    Object.values(data.street || {}).forEach(value => assert.ok(value.length <= 255, 'Native street validation'));
}
const ordinaryProviderSet = provider.set;
provider.set = (path, data) => {
    if (path === 'shippingAddress') validateNativeTextFields(data);
    return ordinaryProviderSet(path, data);
};
const loggedShipping = Object.create(definition);
loggedShipping.name = 'fixture-logged-shipping';
loggedShipping.isFormInline = false;
loggedShipping.source = provider;
loggedShipping.errorValidationMessage = value => { loggedShipping.error = value; };
loggedShipping._super = () => {
    // Native initialization restores persisted form data before the mixin's
    // own address subscription has been registered.
    provider.set('shippingAddress', checkoutData.getShippingAddressFromData());
    return loggedShipping;
};
assert.doesNotThrow(() => loggedShipping.initialize(), 'A logged-in ammo checkout must initialize with null optional fields.');
assert.strictEqual(providerData.company, '');
assert.strictEqual(providerData.middlename, '');
assert.strictEqual(providerData.street[1], '');
assert.strictEqual(providerData.custom_attributes.delivery_checkbox, false, 'Custom checkbox values must not become strings.');
assert.strictEqual(providerData.custom_attributes.optional_customer_value, null, 'Custom attribute semantics must be preserved.');
assert.strictEqual(nullableSavedAddress.company, null, 'The original saved account address must not be mutated.');
assert.strictEqual(nullableSavedAddress.street[1], null);
assert.strictEqual(createdAddressCount, previousCreatedAddresses, 'Initial load must reuse the selected saved address.');
assert.strictEqual(quote.shippingAddress(), nullableSavedAddress);
assert.strictEqual(quote.billingAddress(), nullableBilling);
requests.shift().pending.resolve({ route: 'standard', requiresDealer: false });
destination.update('CA');
requests.shift().pending.resolve({ route: 'standard', requiresDealer: true });
// Also repair a home snapshot persisted by an older version of the extension.
rememberedHome = JSON.parse(originalNullableData);
assert.doesNotThrow(() => destination.update('CO'), 'Dealer-to-home restoration must tolerate historical null fields.');
assert.strictEqual(providerData.company, '');
assert.strictEqual(providerData.street[1], '');
assert.strictEqual(quote.shippingAddress(), nullableSavedAddress);
requests.shift().pending.resolve({ route: 'standard', requiresDealer: false });
loggedShipping.destroy();
provider.set = ordinaryProviderSet;
loggedIn(false);

// A guest returns from payment with "same as shipping" selected, switches to
// dealer delivery, and submits shipping again. Magento clears quote billing
// before resolving it from the native selected/new billing address storage.
const guestHomeBilling = { ...home, getCacheKey: () => 'guest-home-billing' };
const billingDealer = { ...selectedDealer, getCacheKey: () => 'selected-dealer',
    street: ['30 Dealer St'], canUseForBilling: () => false };
const billingTransition = Object.create(definition);
billingTransition.autofflConfig = { enabled: true, ammoOnly: true };
billingTransition.syncAmmoShippingState = () => {};
billingTransition.syncDealerRecipient = () => {};
billingTransition.errorValidationMessage = message => { throw new Error(message); };
const savedBillingAddress = { ...guestHomeBilling, getType: () => 'customer-address',
    getKey: () => 'customer-address-10' };
billingTransition._super = () => {
    quote.billingAddress(null);
    if (selectedBillingAddress === 'new-customer-billing-address' && newBillingData) {
        const restoredBilling = converter.formAddressDataToQuoteAddress(newBillingData);
        restoredBilling.getType = () => 'new-customer-billing-address';
        restoredBilling.getCacheKey = () => 'restored-billing';
        quote.billingAddress(restoredBilling);
    } else if (selectedBillingAddress === savedBillingAddress.getKey()) {
        quote.billingAddress(savedBillingAddress);
    }
};
function submitDealerShipping(previousBilling) {
    selectedBillingAddress = null;
    newBillingData = null;
    quote.billingAddress(previousBilling);
    destination.setRequirement(true);
    destination.error('');
    quote.shippingAddress(billingDealer);
    billingTransition.setShippingInformation();
    return quote.billingAddress();
}
const restoredGuestBilling = submitDealerShipping(guestHomeBilling);
assert.ok(restoredGuestBilling, 'Returning to payment with dealer delivery must restore guest billing after the native reset.');
assert.deepStrictEqual(restoredGuestBilling.street, guestHomeBilling.street, 'The original home address remains billing.');
assert.strictEqual(restoredGuestBilling.firstname, guestHomeBilling.firstname);
assert.strictEqual(restoredGuestBilling.regionCode, guestHomeBilling.regionCode);
assert.strictEqual(restoredGuestBilling.getType(), 'new-customer-billing-address');
assert.notStrictEqual(restoredGuestBilling.getCacheKey(), billingDealer.getCacheKey(),
    'Native billing details must expose Edit rather than retain same-as-dealer shipping.');
assert.strictEqual(routing.isDealer(restoredGuestBilling), false);
billingData.street[0] = 'Edited billing';
assert.deepStrictEqual(guestHomeBilling.street, ['10 Home St'], 'Billing storage must be independent of the shipping address object.');
const separateBilling = { ...guestHomeBilling, street: ['20 Billing St'], firstname: 'Separate' };
const restoredSeparateBilling = submitDealerShipping(separateBilling);
assert.deepStrictEqual(restoredSeparateBilling.street, separateBilling.street, 'An independently entered billing address must also survive.');
assert.strictEqual(restoredSeparateBilling.firstname, 'Separate');
const nullableGuestBilling = { ...guestHomeBilling, company: null, middlename: null,
    prefix: null, suffix: null, street: ['10 Home St', null] };
submitDealerShipping(nullableGuestBilling);
assert.strictEqual(newBillingData.company, '', 'Dealer billing restoration must also supply strings to native text validators.');
assert.strictEqual(newBillingData.middlename, '');
assert.strictEqual(newBillingData.street[1], '');
assert.strictEqual(nullableGuestBilling.company, null, 'Billing normalization must not mutate the original address.');
assert.strictEqual(submitDealerShipping(savedBillingAddress), savedBillingAddress, 'Saved customer billing retains its native address selection.');
assert.strictEqual(newBillingData, null, 'Saved customer billing must not become a duplicate new address.');
assert.strictEqual(submitDealerShipping(null), null, 'A fresh dealer checkout must still collect billing through the native form.');
assert.strictEqual(selectedBillingAddress, null);
assert.strictEqual(submitDealerShipping(billingDealer), null, 'A dealer must never be saved as the customer billing address.');
assert.strictEqual(newBillingData, null);
console.log('Shipping routing, immediate transitions, and quote-save ordering checks passed.');
