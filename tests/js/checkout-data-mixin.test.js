const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const moduleSource = fs.readFileSync(
    process.env.CHECKOUT_DATA_MIXIN_PATH || path.resolve(
        __dirname,
        '../../core/view/frontend/web/js/checkout-data-mixin.js'
    ),
    'utf8'
);
const helperSource = fs.readFileSync(
    process.env.FFL_ADDRESS_HELPER_PATH || path.resolve(
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

function clone(value) {
    return value === undefined ? undefined : JSON.parse(JSON.stringify(value));
}

function loadFflAddressHelper() {
    let helper;

    vm.runInNewContext(helperSource, {
        define(dependencies, factory) {
            assert.deepEqual(Array.from(dependencies), ['underscore']);
            helper = factory(underscore);
        }
    }, {
        filename: 'ffl-address.js'
    });

    return helper;
}

const fflAddress = loadFflAddressHelper();

function loadMixin({
    checkoutDataState,
    configBillingAddress = null,
    configShippingAddress = null,
    isFfl = 0,
    standaloneCheckoutDataState = null,
    storeCode = 'default'
}) {
    let mixinFactory;
    let standaloneData = clone(standaloneCheckoutDataState);
    let storedData = clone(checkoutDataState);
    const storage = {
        get(key) {
            assert.equal(key, 'checkout-data');

            return () => storedData;
        },

        set(key, value) {
            assert.equal(key, 'checkout-data');
            storedData = value;
        }
    };
    const localStorage = {
        getItem(key) {
            assert.equal(key, 'checkout-data');

            return standaloneData === null ? null : JSON.stringify(standaloneData);
        },

        setItem(key, value) {
            assert.equal(key, 'checkout-data');
            standaloneData = JSON.parse(value);
        }
    };
    const checkoutConfig = {
        customerData: {
            is_ffl: isFfl
        },
        billingAddressFromData: clone(configBillingAddress),
        selectedShippingMethod: 'ups_02',
        shippingAddressFromData: clone(configShippingAddress),
        storeCode
    };
    const checkoutData = {
        getShippingAddressFromData() {
            return storedData.shippingAddressFromData || null;
        },

        getNewCustomerShippingAddress() {
            return storedData.newCustomerShippingAddress || null;
        }
    };
    const sandbox = {
        define(dependencies, factory) {
            assert.deepEqual(
                Array.from(dependencies),
                [
                    'Magento_Customer/js/customer-data',
                    'RefactoredGroup_AutoFflCore/js/checkout/helper/ffl-address'
                ]
            );
            mixinFactory = factory(storage, fflAddress);
        },
        window: {
            checkoutConfig,
            localStorage
        }
    };

    vm.runInNewContext(moduleSource, sandbox, {
        filename: 'checkout-data-mixin.js'
    });

    assert.equal(typeof mixinFactory, 'function');
    mixinFactory(checkoutData);

    return {
        checkoutConfig,
        getStandaloneData: () => standaloneData,
        getStoredData: () => storedData
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

function scopedDealerIdentity(storeCode = 'default') {
    return {
        [storeCode]: dealerIdentity()
    };
}

function loggedInDealerIdentity() {
    return {
        company: 'RIFLEGEAR',
        firstname: 'Ada',
        lastname: 'Lovelace',
        telephone: '(972)292-7678'
    };
}

function loggedInDealerAddress() {
    return {
        city: 'Lewisville',
        company: 'RIFLEGEAR',
        country_id: 'US',
        firstname: 'Ada',
        lastname: 'Lovelace',
        postcode: '75056-5104',
        region: 'Texas',
        street: ['4001 State Highway 121'],
        telephone: '(972) 292-7678'
    };
}

function vertexDealerAddress() {
    return {
        city: 'Lewisville',
        company: 'RIFLEGEAR',
        country_id: 'US',
        firstname: 'FFL',
        lastname: 'Dealer',
        postcode: '75056-5104',
        street: ['4001 State Highway 121'],
        telephone: '(972) 292-7678'
    };
}

function provenLegacyGuestDealerAddress() {
    return {
        city: 'Lewisville',
        company: 'RIFLEGEAR',
        country_id: 'US',
        firstname: 'FFL',
        lastname: 'Dealer',
        postcode: '75056-5104',
        region: 'Texas',
        street: {
            0: '4001 State Highway 121'
        },
        telephone: '(972)292-7678'
    };
}

function customerAddress() {
    return {
        city: 'Denver',
        company: '',
        country_id: 'US',
        firstname: 'Ada',
        lastname: 'Lovelace',
        postcode: '80202',
        street: ['1701 Wynkoop Street'],
        telephone: '303-555-0100'
    };
}

test('clears a markerless dealer address normalized by Vertex using provenance', () => {
    const dealerAddress = vertexDealerAddress();
    const result = loadMixin({
        checkoutDataState: {
            billingAddressFromData: clone(dealerAddress),
            fflDealerAddressIdentity: scopedDealerIdentity(),
            fflQuoteLineItemId: [123],
            newCustomerBillingAddress: clone(dealerAddress),
            newCustomerShippingAddress: clone(dealerAddress),
            selectedBillingAddress: 'new-customer-billing-address',
            selectedShippingAddress: 'new-customer-address',
            selectedShippingMethod: 'ups_02',
            selectedShippingRate: 'ups_02',
            shippingAddressFromData: clone(dealerAddress)
        },
        configShippingAddress: dealerAddress
    });
    const storedData = result.getStoredData();

    assert.equal(storedData.shippingAddressFromData, null);
    assert.equal(storedData.newCustomerShippingAddress, null);
    assert.equal(storedData.selectedShippingAddress, null);
    assert.equal(storedData.selectedShippingMethod, null);
    assert.equal(storedData.selectedShippingRate, null);
    assert.equal(storedData.fflDealerAddressIdentity, null);
    assert.equal(storedData.fflQuoteLineItemId, false);
    assert.equal(storedData.billingAddressFromData, null);
    assert.equal(storedData.newCustomerBillingAddress, null);
    assert.equal(storedData.selectedBillingAddress, null);
    assert.equal(result.checkoutConfig.shippingAddressFromData, null);
    assert.equal(result.checkoutConfig.selectedShippingMethod, null);
});

test('recovers standalone provenance and clears the proven guest dealer address', () => {
    const dealerAddress = provenLegacyGuestDealerAddress();
    const result = loadMixin({
        checkoutDataState: {
            fflDealerAddressIdentity: null,
            newCustomerShippingAddress: null,
            selectedShippingAddress: 'new-customer-address',
            selectedShippingMethod: 'ups_02',
            selectedShippingRate: 'ups_02',
            shippingAddressFromData: clone(dealerAddress)
        },
        standaloneCheckoutDataState: {
            fflDealerAddressIdentity: scopedDealerIdentity(
                'wilson_combat_store_view'
            ),
            selectedShippingAddress: 'new-customer-address',
            shippingAddressFromData: clone(dealerAddress)
        },
        storeCode: 'wilson_combat_store_view'
    });
    const standaloneData = result.getStandaloneData();
    const storedData = result.getStoredData();

    assert.equal(storedData.shippingAddressFromData, null);
    assert.equal(storedData.newCustomerShippingAddress, null);
    assert.equal(storedData.selectedShippingAddress, null);
    assert.equal(storedData.selectedShippingMethod, null);
    assert.equal(storedData.selectedShippingRate, null);
    assert.equal(storedData.fflDealerAddressIdentity, null);
    assert.equal(standaloneData.shippingAddressFromData, null);
    assert.equal(standaloneData.selectedShippingAddress, null);
    assert.equal(standaloneData.fflDealerAddressIdentity, null);
    assert.equal(result.checkoutConfig.shippingAddressFromData, null);
});

test('recovers only the current store standalone dealer provenance', () => {
    const dealerAddress = provenLegacyGuestDealerAddress();
    const buyerAddress = customerAddress();
    const canonicalStoreIdentity = {
        company: 'CANONICAL STORE DEALER',
        firstname: 'FFL',
        lastname: 'Dealer',
        telephone: '720-555-0100'
    };
    const secondStoreIdentity = {
        company: 'SECOND STORE DEALER',
        firstname: 'FFL',
        lastname: 'Dealer',
        telephone: '303-555-0199'
    };
    const result = loadMixin({
        checkoutDataState: {
            fflDealerAddressIdentity: {
                canonical_store: clone(canonicalStoreIdentity)
            },
            newCustomerShippingAddress: {
                second_store: clone(buyerAddress),
                wilson_combat_store_view: clone(dealerAddress)
            },
            selectedShippingAddress: 'new-customer-address',
            shippingAddressFromData: {
                second_store: clone(buyerAddress),
                wilson_combat_store_view: clone(dealerAddress)
            }
        },
        standaloneCheckoutDataState: {
            fflDealerAddressIdentity: {
                second_store: clone(secondStoreIdentity),
                wilson_combat_store_view: dealerIdentity()
            }
        },
        storeCode: 'wilson_combat_store_view'
    });
    const standaloneData = result.getStandaloneData();
    const storedData = result.getStoredData();

    assert.deepEqual(storedData.shippingAddressFromData, {
        second_store: buyerAddress
    });
    assert.deepEqual(storedData.newCustomerShippingAddress, {
        second_store: buyerAddress
    });
    assert.deepEqual(clone(storedData.fflDealerAddressIdentity), {
        canonical_store: canonicalStoreIdentity,
        second_store: secondStoreIdentity
    });
    assert.equal(storedData.selectedShippingAddress, null);
    assert.deepEqual(clone(standaloneData), clone(storedData));
});

test('recovers standalone logged-in provenance and clears the dealer address', () => {
    const dealerAddress = loggedInDealerAddress();
    const result = loadMixin({
        checkoutDataState: {
            fflDealerAddressIdentity: null,
            newCustomerShippingAddress: clone(dealerAddress),
            selectedShippingAddress: 'new-customer-address',
            selectedShippingMethod: 'ups_02',
            selectedShippingRate: 'ups_02',
            shippingAddressFromData: clone(dealerAddress)
        },
        configShippingAddress: dealerAddress,
        standaloneCheckoutDataState: {
            fflDealerAddressIdentity: {
                default: loggedInDealerIdentity()
            }
        }
    });
    const standaloneData = result.getStandaloneData();
    const storedData = result.getStoredData();

    assert.equal(storedData.shippingAddressFromData, null);
    assert.equal(storedData.newCustomerShippingAddress, null);
    assert.equal(storedData.selectedShippingAddress, null);
    assert.equal(storedData.selectedShippingMethod, null);
    assert.equal(storedData.selectedShippingRate, null);
    assert.equal(storedData.fflDealerAddressIdentity, null);
    assert.equal(standaloneData.fflDealerAddressIdentity, null);
    assert.equal(standaloneData.shippingAddressFromData, null);
    assert.equal(result.checkoutConfig.shippingAddressFromData, null);
});

test('preserves a markerless logged-in business address without provenance', () => {
    const buyerAddress = {
        city: 'Denver',
        company: 'Analytical Engines LLC',
        country_id: 'US',
        firstname: 'Ada',
        lastname: 'Lovelace',
        postcode: '80202',
        region: 'Colorado',
        street: ['1701 Wynkoop Street'],
        telephone: '303-555-0100'
    };
    const originalData = {
        newCustomerShippingAddress: clone(buyerAddress),
        selectedShippingAddress: 'new-customer-address',
        selectedShippingMethod: 'ups_02',
        selectedShippingRate: 'ups_02',
        shippingAddressFromData: clone(buyerAddress)
    };
    const result = loadMixin({
        checkoutDataState: originalData,
        configShippingAddress: buyerAddress
    });

    assert.deepEqual(result.getStoredData(), originalData);
    assert.deepEqual(result.checkoutConfig.shippingAddressFromData, buyerAddress);
    assert.equal(result.checkoutConfig.selectedShippingMethod, 'ups_02');
});

test('preserves a logged-in buyer when only standalone provenance remains', () => {
    const buyerAddress = customerAddress();
    const originalData = {
        newCustomerShippingAddress: clone(buyerAddress),
        selectedShippingAddress: 'customer-address-42',
        selectedShippingMethod: 'ups_02',
        selectedShippingRate: 'ups_02',
        shippingAddressFromData: clone(buyerAddress)
    };
    const result = loadMixin({
        checkoutDataState: originalData,
        configShippingAddress: buyerAddress,
        standaloneCheckoutDataState: {
            fflDealerAddressIdentity: {
                default: loggedInDealerIdentity()
            }
        }
    });
    const standaloneData = result.getStandaloneData();
    const storedData = result.getStoredData();

    assert.deepEqual(storedData.shippingAddressFromData, buyerAddress);
    assert.deepEqual(storedData.newCustomerShippingAddress, buyerAddress);
    assert.equal(storedData.selectedShippingAddress, 'customer-address-42');
    assert.equal(storedData.selectedShippingMethod, 'ups_02');
    assert.equal(storedData.selectedShippingRate, 'ups_02');
    assert.equal(storedData.fflDealerAddressIdentity, null);
    assert.equal(standaloneData.fflDealerAddressIdentity, null);
    assert.deepEqual(standaloneData.shippingAddressFromData, buyerAddress);
    assert.deepEqual(result.checkoutConfig.shippingAddressFromData, buyerAddress);
    assert.equal(result.checkoutConfig.selectedShippingMethod, 'ups_02');
});

test('preserves an ordinary customer address when there is no dealer provenance', () => {
    const buyerAddress = customerAddress();
    const originalData = {
        newCustomerShippingAddress: clone(buyerAddress),
        selectedShippingAddress: 'new-customer-address',
        selectedShippingMethod: 'ups_02',
        selectedShippingRate: 'ups_02',
        shippingAddressFromData: clone(buyerAddress)
    };
    const result = loadMixin({
        checkoutDataState: originalData,
        configShippingAddress: buyerAddress
    });

    assert.deepEqual(result.getStoredData(), originalData);
    assert.deepEqual(result.checkoutConfig.shippingAddressFromData, buyerAddress);
    assert.equal(result.checkoutConfig.selectedShippingMethod, 'ups_02');
});

test('clears the previous dealer before an FFL checkout resolves its address', () => {
    const dealerAddress = vertexDealerAddress();
    const buyerAddress = customerAddress();
    const result = loadMixin({
        checkoutDataState: {
            billingAddressFromData: clone(buyerAddress),
            fflDealerAddressIdentity: scopedDealerIdentity(),
            newCustomerBillingAddress: clone(buyerAddress),
            newCustomerShippingAddress: {
                default: clone(dealerAddress)
            },
            selectedBillingAddress: 'customer-address-42',
            selectedShippingAddress: 'new-customer-address',
            selectedShippingMethod: 'ups_02',
            selectedShippingRate: 'ups_02',
            shippingAddressFromData: {
                default: clone(dealerAddress)
            }
        },
        configBillingAddress: buyerAddress,
        configShippingAddress: dealerAddress,
        isFfl: 1
    });
    const storedData = result.getStoredData();

    assert.equal(storedData.shippingAddressFromData, null);
    assert.equal(storedData.newCustomerShippingAddress, null);
    assert.equal(storedData.selectedShippingAddress, null);
    assert.equal(storedData.selectedShippingMethod, null);
    assert.equal(storedData.selectedShippingRate, null);
    assert.equal(storedData.fflDealerAddressIdentity, null);
    assert.deepEqual(storedData.billingAddressFromData, buyerAddress);
    assert.deepEqual(storedData.newCustomerBillingAddress, buyerAddress);
    assert.equal(storedData.selectedBillingAddress, 'customer-address-42');
    assert.equal(result.checkoutConfig.shippingAddressFromData, null);
    assert.deepEqual(result.checkoutConfig.billingAddressFromData, buyerAddress);
    assert.equal(result.checkoutConfig.selectedShippingMethod, null);
});

test('FFL checkout clears a customer shipping form but preserves customer billing', () => {
    const buyerAddress = customerAddress();
    const result = loadMixin({
        checkoutDataState: {
            billingAddressFromData: clone(buyerAddress),
            newCustomerBillingAddress: clone(buyerAddress),
            newCustomerShippingAddress: {
                default: clone(buyerAddress)
            },
            selectedBillingAddress: 'customer-address-42',
            selectedShippingAddress: 'customer-address-42',
            shippingAddressFromData: {
                default: clone(buyerAddress)
            }
        },
        configBillingAddress: buyerAddress,
        configShippingAddress: buyerAddress,
        isFfl: 1
    });
    const storedData = result.getStoredData();

    assert.equal(storedData.shippingAddressFromData, null);
    assert.equal(storedData.newCustomerShippingAddress, null);
    assert.equal(storedData.selectedShippingAddress, null);
    assert.deepEqual(storedData.billingAddressFromData, buyerAddress);
    assert.deepEqual(storedData.newCustomerBillingAddress, buyerAddress);
    assert.equal(storedData.selectedBillingAddress, 'customer-address-42');
    assert.equal(result.checkoutConfig.shippingAddressFromData, null);
    assert.deepEqual(result.checkoutConfig.billingAddressFromData, buyerAddress);
});

test('FFL reset preserves checkout data belonging to another store', () => {
    const dealerAddress = vertexDealerAddress();
    const buyerAddress = customerAddress();
    const secondStoreIdentity = {
        company: 'SECOND STORE DEALER',
        firstname: 'FFL',
        lastname: 'Dealer',
        telephone: '303-555-0199'
    };
    const result = loadMixin({
        checkoutDataState: {
            fflDealerAddressIdentity: {
                default: dealerIdentity(),
                second_store: clone(secondStoreIdentity)
            },
            newCustomerShippingAddress: {
                default: clone(dealerAddress),
                second_store: clone(buyerAddress)
            },
            selectedShippingAddress: 'new-customer-address',
            shippingAddressFromData: {
                default: clone(dealerAddress),
                second_store: clone(buyerAddress)
            }
        },
        configShippingAddress: dealerAddress,
        isFfl: 1
    });
    const storedData = result.getStoredData();

    assert.deepEqual(storedData.shippingAddressFromData, {
        second_store: buyerAddress
    });
    assert.deepEqual(storedData.newCustomerShippingAddress, {
        second_store: buyerAddress
    });
    assert.deepEqual(storedData.fflDealerAddressIdentity, {
        second_store: secondStoreIdentity
    });
});

test('preserves buyer billing data while removing the stale dealer shipping data', () => {
    const dealerAddress = vertexDealerAddress();
    const buyerAddress = customerAddress();
    const result = loadMixin({
        checkoutDataState: {
            billingAddressFromData: clone(buyerAddress),
            fflDealerAddressIdentity: scopedDealerIdentity(),
            newCustomerBillingAddress: clone(buyerAddress),
            newCustomerShippingAddress: clone(dealerAddress),
            selectedBillingAddress: 'new-customer-billing-address',
            selectedShippingAddress: 'new-customer-address',
            shippingAddressFromData: clone(dealerAddress)
        },
        configShippingAddress: dealerAddress
    });
    const storedData = result.getStoredData();

    assert.equal(storedData.shippingAddressFromData, null);
    assert.equal(storedData.newCustomerShippingAddress, null);
    assert.deepEqual(storedData.billingAddressFromData, buyerAddress);
    assert.deepEqual(storedData.newCustomerBillingAddress, buyerAddress);
    assert.equal(storedData.selectedBillingAddress, 'new-customer-billing-address');
});

test('removes only the current store address from store-scoped checkout data', () => {
    const dealerAddress = vertexDealerAddress();
    const buyerAddress = customerAddress();
    const result = loadMixin({
        checkoutDataState: {
            fflDealerAddressIdentity: {
                default: dealerIdentity(),
                second_store: {
                    company: 'SECOND STORE DEALER',
                    firstname: 'FFL',
                    lastname: 'Dealer',
                    telephone: '303-555-0199'
                }
            },
            newCustomerShippingAddress: {
                default: clone(dealerAddress),
                second_store: clone(buyerAddress)
            },
            selectedShippingAddress: 'new-customer-address',
            shippingAddressFromData: {
                default: clone(dealerAddress),
                second_store: clone(buyerAddress)
            }
        },
        configShippingAddress: dealerAddress
    });
    const storedData = result.getStoredData();

    assert.deepEqual(storedData.shippingAddressFromData, {
        second_store: buyerAddress
    });
    assert.deepEqual(storedData.newCustomerShippingAddress, {
        second_store: buyerAddress
    });
    assert.deepEqual(storedData.fflDealerAddressIdentity, {
        second_store: {
            company: 'SECOND STORE DEALER',
            firstname: 'FFL',
            lastname: 'Dealer',
            telephone: '303-555-0199'
        }
    });
});

test('does not clear buyer state when only stale provenance remains', () => {
    const buyerAddress = customerAddress();
    const result = loadMixin({
        checkoutDataState: {
            fflDealerAddressIdentity: scopedDealerIdentity(),
            newCustomerShippingAddress: {
                default: clone(buyerAddress)
            },
            selectedShippingAddress: 'customer-address-42',
            selectedShippingMethod: 'ups_02',
            selectedShippingRate: 'ups_02',
            shippingAddressFromData: {
                default: clone(buyerAddress)
            }
        },
        configShippingAddress: buyerAddress
    });
    const storedData = result.getStoredData();

    assert.deepEqual(storedData.shippingAddressFromData, {
        default: buyerAddress
    });
    assert.deepEqual(storedData.newCustomerShippingAddress, {
        default: buyerAddress
    });
    assert.equal(storedData.selectedShippingAddress, 'customer-address-42');
    assert.equal(storedData.selectedShippingMethod, 'ups_02');
    assert.equal(storedData.selectedShippingRate, 'ups_02');
    assert.equal(storedData.fflDealerAddressIdentity, null);
    assert.deepEqual(result.checkoutConfig.shippingAddressFromData, buyerAddress);
    assert.equal(result.checkoutConfig.selectedShippingMethod, 'ups_02');
});

test('does not apply another store provenance to the current store', () => {
    const dealerAddress = vertexDealerAddress();
    const buyerAddress = customerAddress();
    const originalData = {
        fflDealerAddressIdentity: scopedDealerIdentity('default'),
        newCustomerShippingAddress: {
            default: clone(dealerAddress),
            second_store: clone(buyerAddress)
        },
        selectedShippingAddress: 'customer-address-42',
        selectedShippingMethod: 'ups_02',
        selectedShippingRate: 'ups_02',
        shippingAddressFromData: {
            default: clone(dealerAddress),
            second_store: clone(buyerAddress)
        }
    };
    const result = loadMixin({
        checkoutDataState: originalData,
        configShippingAddress: buyerAddress,
        storeCode: 'second_store'
    });

    assert.deepEqual(result.getStoredData(), originalData);
    assert.deepEqual(result.checkoutConfig.shippingAddressFromData, buyerAddress);
    assert.equal(result.checkoutConfig.selectedShippingMethod, 'ups_02');
});

test('preserves another store identity while clearing a marked current-store address', () => {
    const markedDealerAddress = {
        ...vertexDealerAddress(),
        is_ffl: '1'
    };
    const secondStoreIdentity = scopedDealerIdentity('second_store');
    const result = loadMixin({
        checkoutDataState: {
            fflDealerAddressIdentity: secondStoreIdentity,
            newCustomerShippingAddress: {
                default: clone(markedDealerAddress)
            },
            selectedShippingAddress: 'new-customer-address',
            shippingAddressFromData: {
                default: clone(markedDealerAddress)
            }
        },
        configShippingAddress: markedDealerAddress
    });

    assert.deepEqual(result.getStoredData().fflDealerAddressIdentity, secondStoreIdentity);
});

test('preserves a logged-in saved-address selection after dealer form cleanup', () => {
    const dealerAddress = vertexDealerAddress();
    const result = loadMixin({
        checkoutDataState: {
            fflDealerAddressIdentity: scopedDealerIdentity(),
            newCustomerShippingAddress: {
                default: clone(dealerAddress)
            },
            selectedShippingAddress: 'customer-address-42',
            shippingAddressFromData: {
                default: clone(dealerAddress)
            }
        },
        configShippingAddress: dealerAddress
    });
    const storedData = result.getStoredData();

    assert.equal(storedData.shippingAddressFromData, null);
    assert.equal(storedData.newCustomerShippingAddress, null);
    assert.equal(storedData.selectedShippingAddress, 'customer-address-42');
});

test('clears markerless dealer billing supplied by checkout config', () => {
    const dealerAddress = vertexDealerAddress();
    const buyerAddress = customerAddress();
    const result = loadMixin({
        checkoutDataState: {
            fflDealerAddressIdentity: scopedDealerIdentity(),
            selectedShippingAddress: 'customer-address-42'
        },
        configBillingAddress: dealerAddress,
        configShippingAddress: buyerAddress
    });

    assert.equal(result.checkoutConfig.billingAddressFromData, null);
    assert.deepEqual(result.checkoutConfig.shippingAddressFromData, buyerAddress);
    assert.equal(result.checkoutConfig.selectedShippingMethod, 'ups_02');
});

test('continues to clear addresses that retain an AutoFFL marker', () => {
    const markedDealerAddress = {
        ...vertexDealerAddress(),
        is_ffl: '1'
    };
    const result = loadMixin({
        checkoutDataState: {
            newCustomerShippingAddress: clone(markedDealerAddress),
            selectedShippingAddress: 'new-customer-address',
            shippingAddressFromData: clone(markedDealerAddress)
        },
        configShippingAddress: markedDealerAddress
    });
    const storedData = result.getStoredData();

    assert.equal(storedData.shippingAddressFromData, null);
    assert.equal(storedData.newCustomerShippingAddress, null);
    assert.equal(storedData.selectedShippingAddress, null);
});
