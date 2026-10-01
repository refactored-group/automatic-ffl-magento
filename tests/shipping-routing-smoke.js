// Contract checks for native checkout gating, modal confirmation, and address preservation.
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

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
        enabled: true, ammoOnly: false, stateUrl: '/autoffl/routing/state', formKey: 'fixture'
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
const home = { countryId: 'US', regionCode: 'CA', regionId: 12, firstname: 'Jane', lastname: 'Buyer',
    street: ['10 Home St'], city: 'Home City', postcode: '90210', telephone: '5551231234' };
const split = { route: 'multishipping', requiresDealer: true, requiresLogin: true, url: '/multishipping/checkout' };
assert.ok(routing.isDealer({ customAttributes: [{ attribute_code: 'ffl_license', value: 'fixture' }] }),
    'A native custom-attribute dealer license must not be treated as the home destination.');
routing.continueTo(split);
assert.strictEqual(navigations.length, 0, 'A split must await explicit confirmation.');
assert.match(modals[0].content, /Sign in or create an account/);
modals[0].actions.confirm();
assert.strictEqual(navigations.pop(), split.url);
routing.continueTo({ ...split, requiresLogin: false });
assert.doesNotMatch(modals[1].content, /Sign in/);
routing.continueTo({ route: 'unavailable', url: '/checkout/cart' });
assert.strictEqual(modals[2].buttons[1].text, 'Return to cart');
assert.match(modals[2].content, /separate orders/);
routing.check(window.checkoutConfig.autofflRouting, { ...home, ffl_dealer_data: 'secret', customAttributes: ['secret'] });
assert.strictEqual(requests.pop().options.data.address.includes('secret'), false, 'Address handoff must omit dealer metadata.');

let currentAddress = home;
let billingData;
let savedShipping;
let nativeCalls = 0;
let subscription;
let timer;
const quote = {
    shippingAddress: Object.assign(() => currentAddress, { subscribe: fn => { subscription = fn; return { dispose() {} }; } }),
    billingAddress: () => null
};
let definition;
vm.runInNewContext(fs.readFileSync('checkout/view/frontend/web/js/view/shipping-routing-mixin.js', 'utf8'), {
    window,
    setTimeout: fn => { timer = fn; return 1; }, clearTimeout: () => { timer = null; },
    define: (_, factory) => { factory(quote, {
        setShippingAddressFromData: data => { savedShipping = data; },
        getBillingAddressFromData: () => billingData,
        setBillingAddressFromData: data => { billingData = data; }
    }, { quoteAddressToFormAddressData: value => ({ firstname: value.firstname, street: value.street }) },
    routing, value => value)({ extend: value => { definition = value; return value; } }); }
});
function view() {
    const result = Object.create(definition);
    result._super = () => result;
    result.errorValidationMessage = value => { result.error = value; };
    result.validateShippingInformation = () => true;
    result.initialize();
    result._super = () => { nativeCalls++; };
    return result;
}
const shipping = view();
shipping.setShippingInformation();
assert.strictEqual(nativeCalls, 0, 'Payment cannot advance before server routing resolves.');
requests.pop().pending.resolve(split);
assert.strictEqual(nativeCalls, 0, 'A mixed destination must not submit standard shipping.');
assert.deepStrictEqual(savedShipping.street, home.street);
assert.deepStrictEqual(billingData.street, home.street, 'The home billing address must survive dealer delivery.');
shipping.setShippingInformation();
requests.pop().pending.resolve({ route: 'standard', requiresDealer: false, url: '/checkout' });
assert.strictEqual(nativeCalls, 1, 'An unrestricted cart may continue in native checkout.');
shipping.setShippingInformation();
requests.pop().pending.reject({ responseJSON: { error: 'retry routing' } });
assert.strictEqual(nativeCalls, 1, 'A failed routing check must block payment.');
assert.strictEqual(shipping.error, 'retry routing');
shipping.setShippingInformation();
const stale = requests.pop().pending;
currentAddress = { ...home, regionCode: 'CO', regionId: 13 };
stale.resolve({ route: 'standard', requiresDealer: false });
assert.strictEqual(nativeCalls, 1, 'A response for an old destination must not advance payment.');

window.checkoutConfig.autofflRouting.ammoOnly = true;
currentAddress = home;
const ammoOnly = view();
assert.ok(subscription && timer, 'Ammo-only state selection must be observed without a preflight prompt.');
timer();
requests.pop().pending.resolve({ route: 'standard', requiresDealer: true, url: '/checkout/dealer' });
assert.strictEqual(navigations.pop(), '/checkout/dealer', 'Restricted ammo-only delivery must load the dealer selector.');
currentAddress = { ...home, dealer_license: 'fixture-license' };
ammoOnly.setShippingInformation();
assert.strictEqual(nativeCalls, 2, 'A dealer address must not be used to overwrite the original destination state.');
console.log('Shipping routing smoke checks passed.');
