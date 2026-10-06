const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
function observable(value) {
    const listeners = [];
    const fn = function (next) {
        if (!arguments.length) return value;
        if (value !== next) { value = next; listeners.slice().forEach(listener => listener(value)); }
    };
    fn.subscribe = listener => {
        assert.strictEqual(typeof listener, 'function', 'Do not call observable-array helpers as dealer slots.');
        listeners.push(listener);
        return { dispose() { listeners.splice(listeners.indexOf(listener), 1); } };
    };
    return fn;
}
const ko = { observable, observableArray: value => observable(value || []) };
const dealerEvents = [];
const jquery = () => ({ length: 0, modal() {}, on() {}, off() {}, trigger(event) { dealerEvents.push(event); } });
let definition;
let groupedSlots = [];
vm.runInNewContext(fs.readFileSync('core/view/frontend/web/js/cart/select-dealer-button.js', 'utf8'), {
    define: (_, factory) => { definition = factory(jquery, { extend: data => data }, ko,
        { getFflQuoteLineItemId: () => groupedSlots, setFflQuoteLineItemId: value => { groupedSlots = value; } },
        { isMultishipping: () => true }); }
});
const buttons = [0, 1].map(id => {
    const button = Object.create(definition);
    Object.assign(button, { dealerButtonId: id, recipient_firstname: 'Jane', recipient_lastname: 'Buyer',
        routingState: 'CA', recipient_address_id: 10, _super() {} });
    button.initialize();
    button.dealerAddressId[id](41);
    return button;
});
buttons[0].recipientFirstName[0]('Zoë');
buttons[0].recipientLastName[0]("O'Neil-Smith");
assert.strictEqual(buttons[1].recipientFirstName[1](), 'Zoë');
assert.strictEqual(buttons[1].recipientLastName[1](), "O'Neil-Smith");
buttons[0].openSelectDealerModal();
let request;
jquery.ajax = options => {
    request = options;
    return { done(callback) { callback({ id: 42, name: "Zoë O'Neil-Smith, Dealer Business" }); return this; }, fail() { return this; } };
};
let popupDefinition;
vm.runInNewContext(fs.readFileSync('core/view/frontend/web/js/cart/dealers-popup.js', 'utf8'), {
    window: { location: { href: '/multishipping/checkout/addresses' } },
    define: (_, factory) => { popupDefinition = factory(jquery, { extend: data => data }, ko, () => {},
        () => buttons[0], { getFflQuoteLineItemId: () => [0, 1] }); }
});
const popup = Object.create(popupDefinition);
popup.currentFflItemId = () => 0;
popup.applySelectedDealer({ id: '921', license: 'fixture-license', company: 'Dealer Business',
    firstName: 'Dealer', lastName: 'Contact' });
const selectedPayload = Object.fromEntries(request.data.map(field => [field.name,field.value]));
assert.strictEqual(selectedPayload.recipient_first_name, 'Zoë', 'Multishipping must submit shopper names rather than map contacts.');
assert.strictEqual(selectedPayload.recipient_last_name, "O'Neil-Smith");
assert.strictEqual(selectedPayload.recipient_address_id, 10, 'Dealer creation must use the owned saved address as the recipient source.');
assert.deepStrictEqual(Array.from(groupedSlots), [0, 1], 'Explicit multishipping must group all dealer-required slots.');
assert.strictEqual(buttons[1].dealerAddressId[1](), 42);
assert.deepStrictEqual(dealerEvents, ['automaticffl:dealer-selected'],
    'A successful map selection must refresh the shipping summary and persist the shared dealer group.');
buttons[0].recipientFirstName[0]('Edited');
assert.strictEqual(buttons[1].recipientFirstName[1](), 'Edited', 'All rows sharing a dealer address must keep recipient edits consistent.');
buttons[0].changeRecipientName();
buttons[0].draftFirstName('  New '); buttons[0].draftLastName('Recipient');
buttons[0].saveRecipientName();
assert.strictEqual(buttons[1].recipientFirstName[1](),'New');
assert.strictEqual(buttons[0].recipientEdited[0](),true);
assert(dealerEvents.includes('automaticffl:recipient-changed'));
buttons[0].changeRecipientName(); buttons[0].draftFirstName(''); buttons[0].saveRecipientName();
assert.strictEqual(buttons[0].editingRecipient(),true,'Blank names must keep the required editor open.');
assert(buttons[0].recipientError());
buttons.forEach(button => button.destroy());
console.log('Multishipping recipient fields, grouped edits, and map submission checks passed.');

const restored = Object.create(definition);
Object.assign(restored, { dealerButtonId: 3, recipient_firstname: 'Jane', recipient_lastname: 'Buyer',
    selected_address_id: '41', selected_address_label: 'Jane Buyer, Dealer Business', _super() {} });
restored.initialize();
assert.strictEqual(restored.dealerAddressId[3](), '41', 'Saved dealer IDs must survive address updates and reloads.');
assert.strictEqual(restored.dealerAddress[3](), 'Jane Buyer, Dealer Business');
assert.strictEqual(restored.fflButtonLabel(), 'Change Dealer');
restored.destroy();

// Ammo can join and leave an already selected firearm dealer without reloading the page.
const ammo = Object.create(definition);
Object.assign(ammo, {dealerButtonId: 4, isRequiredFfl: false, home_address_id: '10',
    recipient_firstname: 'Jane', recipient_lastname: 'Buyer', _super() {}});
ammo.initialize();
assert.strictEqual(ammo.shippingAddressId(), '10');
assert(!groupedSlots.includes(4), 'Unrestricted ammo must not register as a dealer group member.');
ammo.setDestination('11', 'CA', true);
assert.strictEqual(ammo.shippingAddressId(), 42, 'CA ammo must immediately use the shared firearm dealer ID.');
assert(groupedSlots.includes(4));
ammo.setDestination('10', 'TX', false);
assert.strictEqual(ammo.shippingAddressId(), '10', 'TX must immediately restore the saved home address.');
assert(!groupedSlots.includes(4), 'Dealer changes must not include unrestricted ammo.');
ammo.destroy();

// Standard checkout must collect both names before opening the dealer map.
let checkoutButtonDefinition;
let formData = {};
let opened = 0;
let fieldChecks = 0;
vm.runInNewContext(fs.readFileSync('core/view/frontend/web/js/checkout/select-dealer-button.js', 'utf8'), {
    define: (_, factory) => {
        checkoutButtonDefinition = factory(jquery, { extend: data => data }, ko, {}, {}, {
            async: () => callback => callback({ get: () => formData }),
            get: () => ({ validate() { fieldChecks++; } })
        }, text => text);
    }
});
const checkoutButton = Object.create(checkoutButtonDefinition);
checkoutButton.requiresDealer = () => true;
checkoutButton.recipientValidationMessage = observable('');
checkoutButton.parentName = 'checkout.steps.shipping-step.shippingAddress';
checkoutButton._super = () => { opened++; };
for (const names of [
    { firstname: '', lastname: '' },
    { firstname: 'Alex', lastname: '  ' },
    { firstname: ' ', lastname: 'Customer' },
    { firstname: 'FFL', lastname: 'Dealer' }
]) {
    formData = names;
    checkoutButton.openSelectDealerModal();
    assert.strictEqual(opened, 0, 'Missing names and placeholder names must keep the dealer map closed.');
    assert.match(checkoutButton.recipientValidationMessage(), /first and last name/);
}
assert.strictEqual(fieldChecks, 8, 'Both native name fields must expose their required validation.');
formData = { firstname: '  Zoë ', lastname: "O'Neil-Smith" };
checkoutButton.openSelectDealerModal();
assert.strictEqual(opened, 1, 'Complete shopper names allow dealer selection.');
assert.strictEqual(checkoutButton.recipientValidationMessage(), '');
console.log('Checkout dealer-map recipient requirement checks passed.');
