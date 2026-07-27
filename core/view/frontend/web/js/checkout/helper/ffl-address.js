define([
    'underscore'
], function (_) {
    'use strict';

    return {
        getAddressValue: function (address, field) {
            var value;

            if (!address || _.isUndefined(address[field]) || _.isNull(address[field])) {
                return null;
            }

            value = address[field];

            if (_.isFunction(value)) {
                value = value.call(address);
            }

            if (_.isObject(value) && !_.isUndefined(value.value)) {
                return value.value;
            }

            return value;
        },

        getAttributeValue: function (attributes, code) {
            var attribute = null;

            if (!attributes) {
                return null;
            }

            if (_.isFunction(attributes)) {
                attributes = attributes();
            }

            if (_.isArray(attributes)) {
                attribute = attributes.find(function (element) {
                    return element.attribute_code === code;
                });
            } else if (_.isObject(attributes) && !_.isUndefined(attributes[code])) {
                attribute = attributes[code];
            } else if (_.isObject(attributes) && attributes.attribute_code === code) {
                attribute = attributes;
            }

            if (_.isNull(attribute) || _.isUndefined(attribute)) {
                return null;
            }

            if (_.isObject(attribute) && !_.isUndefined(attribute.value)) {
                return attribute.value;
            }

            return attribute;
        },

        normalizeIdentityValue: function (value) {
            if (_.isNull(value) || _.isUndefined(value)) {
                return '';
            }

            return String(value).toLowerCase().replace(/[^a-z0-9]/g, '');
        },

        matchesDealerIdentity: function (address, dealerIdentity) {
            var fields = ['firstname', 'lastname', 'company', 'telephone'],
                matchedFields = 0,
                matches = true,
                self = this;

            if (!address || !dealerIdentity) {
                return false;
            }

            fields.forEach(function (field) {
                var expected = self.normalizeIdentityValue(
                        self.getAddressValue(dealerIdentity, field)
                    ),
                    actual;

                if (!expected) {
                    return;
                }

                actual = self.normalizeIdentityValue(self.getAddressValue(address, field));
                matchedFields += 1;

                if (actual !== expected) {
                    matches = false;
                }
            });

            return matches && matchedFields >= 3;
        },

        isDealerAddress: function (address) {
            var isFfl = this.getAddressValue(address, 'is_ffl');

            if (isFfl === true || isFfl === 1 || isFfl === '1') {
                return true;
            }

            return Boolean(
                this.getAddressValue(address, 'dealer_license') ||
                this.getAddressValue(address, 'ffl_license') ||
                this.getAttributeValue(address && address.custom_attributes, 'ffl_license') ||
                this.getAttributeValue(address && address.customAttributes, 'ffl_license') ||
                this.getAttributeValue(address && address.extension_attributes, 'ffl_license') ||
                this.getAttributeValue(address && address.extensionAttributes, 'ffl_license')
            );
        },

        isDealerDerivedAddress: function (address, dealerIdentity) {
            return this.isDealerAddress(address) ||
                this.matchesDealerIdentity(address, dealerIdentity);
        }
    };
});
