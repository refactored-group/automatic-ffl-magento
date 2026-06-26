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
        }
    };
});
