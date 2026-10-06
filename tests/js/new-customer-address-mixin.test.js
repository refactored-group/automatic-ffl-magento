const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const moduleSource = fs.readFileSync(
    path.resolve(
        __dirname,
        '../../checkout/view/frontend/web/js/checkout/model/new-customer-address-mixin.js'
    ),
    'utf8'
);

function loadModel(isFfl) {
    let mixin;
    const wrapper = {
        wrap(original, interceptor) {
            return function () {
                return interceptor.apply(this, [original].concat(Array.from(arguments)));
            };
        }
    };
    const fflAddress = {
        isDealerAddress(address) {
            return address && address.is_ffl === 1;
        }
    };

    vm.runInNewContext(moduleSource, {
        checkoutConfig: {customerData: {is_ffl: isFfl}},
        define(dependencies, factory) {
            assert.deepEqual(Array.from(dependencies), [
                'mage/utils/wrapper',
                'RefactoredGroup_AutoFflCore/js/checkout/helper/ffl-address'
            ]);
            mixin = factory(wrapper, fflAddress);
        }
    }, {filename: 'new-customer-address-mixin.js'});

    return mixin;
}

function addressModel(nativeEligibility) {
    return function (address) {
        return {
            address,
            canUseForBilling() {
                assert.equal(this.address, address);
                return nativeEligibility;
            }
        };
    };
}

test('keeps a shopper address eligible during dealer-required checkout', () => {
    const model = loadModel(1)(addressModel(true));

    assert.equal(model({is_ffl: 0}).canUseForBilling(), true);
});

test('rejects a dealer address for billing', () => {
    const model = loadModel(1)(addressModel(true));

    assert.equal(model({is_ffl: 1}).canUseForBilling(), false);
});

test('preserves native billing restrictions for non-dealer addresses', () => {
    const model = loadModel(0)(addressModel(false));

    assert.equal(model({is_ffl: 0}).canUseForBilling(), false);
});
