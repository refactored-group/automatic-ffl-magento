// Run with node to check the iframe message boundary without a Magento browser.
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const iframeWindow = {};
const iframe = { contentWindow: iframeWindow };
const observable = (initial) => {
  let value = initial;
  return function (next) {
    if (arguments.length) value = next;
    return value;
  };
};
let component;
const context = {
  window: { addEventListener() {}, removeEventListener() {} },
  document: { getElementById: () => iframe },
  define: (_dependencies, factory) => {
    component = factory(
      () => ({ modal() {} }),
      { extend: (definition) => definition },
      { observable },
      () => {},
      () => ({ currentFflItemId: { subscribe() { return { dispose() {} }; } } }),
      {}
    );
  }
};
vm.runInNewContext(
  fs.readFileSync('core/view/frontend/web/js/cart/dealers-popup.js', 'utf8'),
  context
);

const popup = Object.create(component);
popup.iframeOrigin = 'https://static.automaticffl.com';
popup.modalActive = true;
popup.applyingSelection = false;
popup.selectionError = observable('');
let selected = null;
popup.applySelectedDealer = (dealer) => { selected = dealer; };
const validDealer = {
  id: 921,
  company: 'Fixture Dealer',
  fflID: '1-23-456-78-9A-12345',
  address1: '10 Main St',
  city: 'Denver',
  stateOrProvinceCode: 'CO',
  postalCode: '80202',
  countryCode: 'US'
};
const message = (origin, source, value) => ({
  origin, source, data: { type: 'dealerUpdate', value }
});

popup.onIframeMessage(message('https://evil.example', iframeWindow, validDealer));
assert.strictEqual(selected, null, 'wrong origin must be ignored');
popup.onIframeMessage(message(popup.iframeOrigin, {}, validDealer));
assert.strictEqual(selected, null, 'wrong source must be ignored');
popup.onIframeMessage(message(popup.iframeOrigin, iframeWindow, { ...validDealer, id: 0 }));
assert.strictEqual(selected, null, 'invalid dealer must be ignored');
assert.ok(popup.selectionError(), 'invalid dealer should produce a retryable error');
popup.onIframeMessage(message(popup.iframeOrigin, iframeWindow, validDealer));
assert.strictEqual(selected.id, '921', 'dealer ID must be used as an ID, not a result index');
assert.strictEqual(selected.countryCode, 'US');
selected = null;
popup.onIframeMessage(message(popup.iframeOrigin, iframeWindow, validDealer));
assert.strictEqual(selected, null, 'duplicate selection must be ignored while applying');

// Dealer creation runs after immediate map dismissal, without publishing a temporary address ID.
let cartAdapter;
let activeSlots = [0, 1];
const selectionRequests = [];
const selectionEvents = [];
const sharedButton = {
  currentRoutingState: observable('CA'), currentRecipientFirstName: observable('Jane'),
  currentRecipientLastName: observable('Buyer'), currentRecipientAddressId: observable(10),
  currentRecipientOverride: observable(false),
  dealerSelectionPending: observable(false), dealerSelectionError: observable(''),
  dealerAddress: [observable('Previous dealer'), observable(''), observable('')],
  dealerDetails: [observable('Previous dealer details'), observable(''), observable('')],
  dealerAddressId: [observable(40), observable(null), observable(null)],
  recipientFirstName: [observable('Jane'), observable('Jane'), observable('Jane')],
  recipientLastName: [observable('Buyer'), observable('Buyer'), observable('Buyer')]
};
const cartJquery = () => ({
  length: 1,
  serializeArray() { return [{name:'ship[0][1][qty]',value:3},{name:'form_key',value:'native-key'}]; },
  modal(action) { selectionEvents.push(action); },
  trigger(event,args) { selectionEvents.push(event); if(event==='automaticffl:dealer-saving') args[0](4); }
});
cartJquery.ajax = (options) => {
  const request = {options}; selectionRequests.push(request);
  return {done(callback) { request.done = callback; return this; },
    fail(callback) { request.fail = callback; return this; }};
};
vm.runInNewContext(fs.readFileSync('core/view/frontend/web/js/cart/dealers-popup.js', 'utf8'), {
  window: {location: {href: '/multishipping/checkout/addresses/'}},
  define: (_, factory) => {
    cartAdapter = factory(cartJquery, {extend: definition => definition}, {observable}, () => {},
      () => sharedButton, {getFflQuoteLineItemId: () => activeSlots});
  }
});
const cart = Object.create(cartAdapter);
cart.currentFflItemId = () => 0;
cart.modalActive = true;
cart.applyingSelection = true;
cart.applySelectedDealer({...popup.normalizeDealer(validDealer), company: 'Chosen dealer'});
assert.strictEqual(cart.modalActive, false, 'Map must close before Magento responds.');
assert(selectionEvents.includes('closeModal'));
assert(selectionEvents.includes('automaticffl:dealer-saving'));
assert.strictEqual(sharedButton.dealerSelectionPending(), true);
assert(sharedButton.dealerAddress[0]().includes('Chosen dealer'), 'The chosen dealer must appear immediately.');
assert.strictEqual(sharedButton.dealerAddressId[0](), 40, 'A pending selection cannot replace a verified address ID.');
activeSlots = [0, 2];
selectionRequests[0].done({id: 41, name: 'Saved dealer'});
assert.strictEqual(sharedButton.dealerAddressId[0](), 41);
assert.strictEqual(sharedButton.dealerAddressId[1](), null, 'Ammo moved to home delivery while saving must remain home delivery.');
assert.strictEqual(sharedButton.dealerAddressId[2](), 41, 'Ammo joining the required group while saving must get the selected dealer.');
assert.strictEqual(sharedButton.dealerSelectionPending(), false);
assert(selectionEvents.includes('automaticffl:dealer-selected'));
assert(selectionRequests[0].options.data.some(field=>field.name==='ship[0][1][qty]'),
  'The dealer save must include native assignments in the same request.');
assert.strictEqual(selectionRequests[0].options.data.filter(field=>field.name==='form_key').length,1);
cart.applySelectedDealer({...popup.normalizeDealer(validDealer), company: 'Failed replacement'});
selectionRequests[1].fail();
assert.strictEqual(sharedButton.dealerAddress[0](), 'Saved dealer', 'A failed replacement must restore the previous label.');
assert.strictEqual(sharedButton.dealerAddressId[0](), 41);
assert(sharedButton.dealerSelectionError(), 'Failure must stay visible after the map closes.');
assert.strictEqual(sharedButton.dealerSelectionPending(), false);
assert(selectionEvents.includes('automaticffl:dealer-save-failed'));
cart.applySelectedDealer({...popup.normalizeDealer(validDealer), company: 'Invalid response'});
selectionRequests[2].done('not json');
assert.strictEqual(sharedButton.dealerAddress[0](), 'Saved dealer');
assert.strictEqual(sharedButton.dealerSelectionPending(), false);
// Native checkoutProvider must receive the full dealer address so delayed
// shipping-rate validation cannot rebuild a partial home-delivery quote.
let checkoutPopup;
let selectedAddress;
let providerAddress;
let dealerIdentity;
const savedCheckout = {};
const jquery = () => ({ modal() {} });
jquery.extend = (_deep, _target, value) => JSON.parse(JSON.stringify(value));
vm.runInNewContext(fs.readFileSync('core/view/frontend/web/js/checkout/dealers-popup.js', 'utf8'), {
  checkoutConfig: { customerData: { is_ffl: 1 } },
  define: (_, factory) => {
    factory(jquery, { extend: definition => { checkoutPopup = definition; } }, {
      setShippingAddressFromData() {}, setNewCustomerShippingAddress() {},
      setFflDealerAddressIdentity(address) { dealerIdentity = JSON.parse(JSON.stringify(address)); }
    }, data => ({ ...data, getKey: () => 'dealer-address' }), address => { selectedAddress = address; },
    { observable }, () => ({ dealerAddressId: { fixture() {} } }),
    { get: () => () => savedCheckout },
    { async: () => callback => callback({ get: () => providerAddress || { firstname: 'Jane', lastname: 'Buyer' }, set: (_key, data) => { providerAddress = data; } }) });
  }
});
const checkout = Object.create(checkoutPopup);
savedCheckout.shippingAddressFromData = { default: { firstname: 'Jane' }, other: { firstname: 'Other' } };
savedCheckout.newCustomerShippingAddress = { other: { firstname: 'Other' } };
const draftsBeforePopup = JSON.stringify(savedCheckout);
checkout.regionJson = '{"US":{}}';
checkout._super = () => checkout;
checkout.initialize();
assert.strictEqual(JSON.stringify(savedCheckout), draftsBeforePopup,
  'Popup initialization must preserve hydrated shopper fields and other-store drafts after early cleanup.');
checkout.currentFflItemId = () => 'fixture';
checkout.getRegionData = () => ({ id: 13, name: 'Colorado' });
checkout.default_firstname = 'FFL';
checkout.default_lastname = 'Dealer';
checkout.saveCheckoutData = () => {};
checkout.applySelectedDealer({ ...popup.normalizeDealer(validDealer), firstName: 'Dealer', lastName: 'Contact' });
assert.strictEqual(providerAddress.custom_attributes.ffl_license, validDealer.fflID);
assert.strictEqual(providerAddress.firstname, 'Jane');
assert.strictEqual(providerAddress.lastname, 'Buyer');
assert.strictEqual(dealerIdentity.company, validDealer.company);
assert.strictEqual(dealerIdentity.firstname, 'Jane', 'Record shopper names for markerless dealer cleanup.');
assert.strictEqual(providerAddress.region_id, 13);
assert.strictEqual(providerAddress.street[0], validDealer.address1);
assert.strictEqual(Array.isArray(providerAddress.street), false, 'Native street fields need nested object updates.');
assert.strictEqual(providerAddress.street[1], '', 'Selecting a one-line dealer clears a previous second street line.');
providerAddress.street[0] = 'Edited provider value';
assert.strictEqual(selectedAddress.street[0], validDealer.address1, 'The selected dealer address must not share mutable form arrays.');

providerAddress.firstname = 'Edited';
providerAddress.lastname = 'Recipient';
checkout.applySelectedDealer({ ...popup.normalizeDealer(validDealer), firstName: 'Dealer', lastName: 'Contact' });
assert.strictEqual(selectedAddress.firstname, 'Edited', 'Changing dealer must preserve typed recipient names.');
assert.strictEqual(selectedAddress.lastname, 'Recipient');
providerAddress.firstname = '';
providerAddress.lastname = '';
checkout.applySelectedDealer(popup.normalizeDealer(validDealer));
assert.strictEqual(selectedAddress.firstname, '', 'Guests must be asked for their name rather than given FFL Dealer.');
assert.strictEqual(selectedAddress.lastname, '');

// Native new-customer-address exposes extensionAttributes in camel case.
// Preserve the dealer snapshot when shipping is submitted after rate updates.
let extendPayload;
const snapshot = JSON.stringify({ license: validDealer.fflID, address1: validDealer.address1 });
const nativeDealerAddress = {
  customAttributes: [{ attribute_code: 'ffl_license', value: validDealer.fflID }],
  extensionAttributes: { ffl_license: validDealer.fflID, ffl_dealer_data: snapshot, other_extension: 'keep' }
};
vm.runInNewContext(fs.readFileSync('core/view/frontend/web/js/action/set-shipping-information.js', 'utf8'), {
  define: (_, factory) => {
    extendPayload = factory({ wrap: (original, fn) => payload => fn(original, payload) }, {
      shippingAddress: () => nativeDealerAddress
    }, { isArray: Array.isArray, isObject: value => value !== null && typeof value === 'object',
      isUndefined: value => value === undefined, isNull: value => value === null, extend: Object.assign })(() => {});
  }
});
const payload = { addressInformation: { shipping_address: nativeDealerAddress,
  billing_address: { extension_attributes: { ffl_license: validDealer.fflID, other_extension: 'keep' } } } };
extendPayload(payload);
assert.strictEqual(payload.addressInformation.extension_attributes.ffl_license, validDealer.fflID);
assert.strictEqual(payload.addressInformation.extension_attributes.ffl_dealer_data, snapshot);
assert.strictEqual(payload.addressInformation.shipping_address.extensionAttributes.ffl_license, undefined,
  'FFL extensions cannot be sent on the nested AddressInterface.');
assert.strictEqual(payload.addressInformation.shipping_address.extensionAttributes.ffl_dealer_data, undefined);
assert.strictEqual(payload.addressInformation.shipping_address.extensionAttributes.other_extension, 'keep');
assert.strictEqual(payload.addressInformation.billing_address.extension_attributes.ffl_license, undefined);
assert.strictEqual(payload.addressInformation.billing_address.extension_attributes.other_extension, 'keep');
assert.strictEqual(nativeDealerAddress.extensionAttributes.ffl_dealer_data, snapshot,
  'Submission must not erase the selected dealer metadata from the live quote.');
console.log('Dealer iframe message and native form synchronization smoke checks passed.');
