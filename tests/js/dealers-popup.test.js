const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const dealerPopupSource = fs.readFileSync(
    path.resolve(
        __dirname,
        '../../core/view/frontend/web/js/checkout/dealers-popup.js'
    ),
    'utf8'
);
const fflAddressSource = fs.readFileSync(
    path.resolve(
        __dirname,
        '../../core/view/frontend/web/js/checkout/helper/ffl-address.js'
    ),
    'utf8'
);

const underscore = {
    isArray: Array.isArray,
    isFunction: (value) => typeof value === 'function',
    isNull: (value) => value === null,
    isObject: (value) => value !== null && typeof value === 'object',
    isUndefined: (value) => value === undefined
};

function loadFflAddress() {
    let helper;

    vm.runInNewContext(fflAddressSource, {
        define(dependencies, factory) {
            assert.deepEqual(Array.from(dependencies), ['underscore']);
            helper = factory(underscore);
        }
    });

    return helper;
}

function loadComponent(configBillingAddress = null) {
    let component;
    const checkoutConfig = {
        billingAddressFromData: configBillingAddress,
        customerData: {
            is_ffl: 1
        },
        storeCode: 'default'
    };
    const jquery = function () {
        return {};
    };
    const componentBase = {
        extend(methods) {
            return methods;
        }
    };
    const storage = {
        get() {
            return () => ({});
        },
        set() {}
    };

    vm.runInNewContext(dealerPopupSource, {
        checkoutConfig,
        define(dependencies, factory) {
            component = factory(
                jquery,
                componentBase,
                {},
                () => {},
                () => {},
                {
                    observable: () => function () {}
                },
                () => ({}),
                storage,
                loadFflAddress()
            );
        },
        window: {
            checkoutConfig,
            localStorage: {
                setItem() {}
            }
        }
    }, {
        filename: 'dealers-popup.js'
    });

    return {
        component,
        checkoutConfig
    };
}

function dealerIdentity() {
    return {
        company: 'RIFLEGEAR',
        firstname: 'FFL',
        lastname: 'Dealer',
        telephone: '(972)292-7678'
    };
}

function dealerAddress() {
    return {
        company: 'RIFLEGEAR',
        firstname: 'FFL',
        lastname: 'Dealer',
        telephone: '(972) 292-7678'
    };
}

function buyerAddress() {
    return {
        company: '',
        firstname: 'Ada',
        lastname: 'Lovelace',
        telephone: '303-555-0100'
    };
}

test('same-page dealer change clears only current-store dealer billing', () => {
    const dealer = dealerAddress();
    const buyer = buyerAddress();
    const result = loadComponent(dealer);
    const data = {
        billingAddressFromData: {
            default: dealer,
            second_store: buyer
        },
        newCustomerBillingAddress: {
            default: dealer,
            second_store: buyer
        },
        selectedBillingAddress: 'new-customer-billing-address'
    };

    result.component.clearFflBillingData(data, dealerIdentity());

    assert.deepEqual(data.billingAddressFromData, {
        second_store: buyer
    });
    assert.deepEqual(data.newCustomerBillingAddress, {
        second_store: buyer
    });
    assert.equal(data.selectedBillingAddress, null);
    assert.equal(result.checkoutConfig.billingAddressFromData, null);
});

test('same-page dealer change preserves buyer billing', () => {
    const buyer = buyerAddress();
    const result = loadComponent(buyer);
    const data = {
        billingAddressFromData: buyer,
        newCustomerBillingAddress: buyer,
        selectedBillingAddress: 'customer-address-42'
    };

    result.component.clearFflBillingData(data, dealerIdentity());

    assert.deepEqual(data.billingAddressFromData, buyer);
    assert.deepEqual(data.newCustomerBillingAddress, buyer);
    assert.equal(data.selectedBillingAddress, 'customer-address-42');
    assert.deepEqual(result.checkoutConfig.billingAddressFromData, buyer);
});
