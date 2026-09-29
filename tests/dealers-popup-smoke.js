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
console.log('Dealer iframe message smoke checks passed.');
