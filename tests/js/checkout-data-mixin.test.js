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

function clone(value) {
    return value === undefined ? undefined : JSON.parse(JSON.stringify(value));
}

function getAddressValue(address, field) {
    let value;

    if (!address || address[field] === undefined || address[field] === null) {
        return null;
    }

    value = address[field];

    if (typeof value === 'function') {
        value = value.call(address);
    }

    if (value && typeof value === 'object' && value.value !== undefined) {
        return value.value;
    }

    return value;
}

function getAttributeValue(attributes, code) {
    let attribute;

    if (!attributes) {
        return null;
    }

    if (typeof attributes === 'function') {
        attributes = attributes();
    }

    if (Array.isArray(attributes)) {
        attribute = attributes.find((item) => item.attribute_code === code);
    } else {
        attribute = attributes[code];
    }

    if (attribute && typeof attribute === 'object' && attribute.value !== undefined) {
        return attribute.value;
    }

    return attribute || null;
}

const fflAddress = {
    getAddressValue,

    normalizeIdentityValue(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value).toLowerCase().replace(/[^a-z0-9]/g, '');
    },

    matchesDealerIdentity(address, identity) {
        let matchedFields = 0;
        let matches = true;

        if (!address || !identity) {
            return false;
        }

        ['firstname', 'lastname', 'company', 'telephone'].forEach((field) => {
            const expected = this.normalizeIdentityValue(getAddressValue(identity, field));

            if (!expected) {
                return;
            }

            matchedFields += 1;

            if (this.normalizeIdentityValue(getAddressValue(address, field)) !== expected) {
                matches = false;
            }
        });

        return matches && matchedFields >= 3;
    },

    isDealerAddress(address) {
        const isFfl = getAddressValue(address, 'is_ffl');

        if (isFfl === true || isFfl === 1 || isFfl === '1') {
            return true;
        }

        return Boolean(
            getAddressValue(address, 'dealer_license') ||
            getAddressValue(address, 'ffl_license') ||
            getAttributeValue(address && address.custom_attributes, 'ffl_license') ||
            getAttributeValue(address && address.customAttributes, 'ffl_license') ||
            getAttributeValue(address && address.extension_attributes, 'ffl_license') ||
            getAttributeValue(address && address.extensionAttributes, 'ffl_license')
        );
    },

    isDealerDerivedAddress(address, identity) {
        return this.isDealerAddress(address) ||
            this.matchesDealerIdentity(address, identity);
    }
};

function getStoredAddress(storedAddress, storeCode) {
    if (storedAddress &&
        Object.prototype.hasOwnProperty.call(storedAddress, storeCode)
    ) {
        return storedAddress[storeCode];
    }

    return storedAddress || null;
}

function loadMixin({
    checkoutDataState,
    configBillingAddress = null,
    configShippingAddress = null,
    isFfl = 0,
    storeCode = 'default'
}) {
    let mixinFactory;
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
            return getStoredAddress(storedData.shippingAddressFromData, storeCode);
        },

        getNewCustomerShippingAddress() {
            return getStoredAddress(storedData.newCustomerShippingAddress, storeCode);
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
            checkoutConfig
        }
    };

    vm.runInNewContext(moduleSource, sandbox, {
        filename: 'checkout-data-mixin.js'
    });

    assert.equal(typeof mixinFactory, 'function');
    mixinFactory(checkoutData);

    return {
        checkoutConfig,
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

test('clears a markerless dealer address normalized by Vertex', () => {
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
