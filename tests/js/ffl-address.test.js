const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const moduleSource = fs.readFileSync(
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

function loadHelper() {
    let helper;

    vm.runInNewContext(moduleSource, {
        define(dependencies, factory) {
            assert.deepEqual(Array.from(dependencies), ['underscore']);
            helper = factory(underscore);
        }
    }, {
        filename: 'ffl-address.js'
    });

    return helper;
}

test('matches the live Vertex-normalized dealer identity', () => {
    const helper = loadHelper();

    assert.equal(helper.isDealerDerivedAddress({
        company: 'RIFLEGEAR',
        firstname: 'FFL',
        lastname: 'Dealer',
        telephone: '(972) 292-7678'
    }, {
        company: 'RIFLEGEAR',
        firstname: 'FFL',
        lastname: 'Dealer',
        telephone: '(972)292-7678'
    }), true);
});

test('does not match an ordinary buyer to dealer provenance', () => {
    const helper = loadHelper();

    assert.equal(helper.isDealerDerivedAddress({
        company: '',
        firstname: 'Ada',
        lastname: 'Lovelace',
        telephone: '303-555-0100'
    }, {
        company: 'RIFLEGEAR',
        firstname: 'FFL',
        lastname: 'Dealer',
        telephone: '(972)292-7678'
    }), false);
});

test('requires at least three populated dealer identity fields', () => {
    const helper = loadHelper();

    assert.equal(helper.matchesDealerIdentity({
        firstname: 'FFL',
        lastname: 'Dealer'
    }, {
        firstname: 'FFL',
        lastname: 'Dealer'
    }), false);
});

test('still recognizes explicit AutoFFL address markers', () => {
    const helper = loadHelper();

    assert.equal(helper.isDealerDerivedAddress({
        custom_attributes: {
            ffl_license: {
                value: '5-75-121-07-6L-10199'
            }
        }
    }, null), true);
});
