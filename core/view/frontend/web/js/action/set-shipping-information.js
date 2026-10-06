/*jshint browser:true jquery:true*/
/*global alert*/
define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'underscore'
], function (wrapper, quote, _) {
    'use strict';

    return function (target) {

        return wrapper.wrap(target, function (parentFunction, payload) {
            parentFunction(payload);

            var shippingAddress = quote.shippingAddress();

            if (!shippingAddress) {
                return payload;
            }

            var addressAttributes = shippingAddress.extensionAttributes ||
                shippingAddress.extension_attributes || {};

            var customAttributes = shippingAddress.customAttributes;
            var attribute = null;
            var fflLicense = null;

            if (_.isArray(customAttributes)) {
                attribute = customAttributes.find(
                    function (element) {
                        return element.attribute_code === 'ffl_license';
                    }
                );
            } else if (_.isObject(customAttributes) && !_.isUndefined(customAttributes['ffl_license'])) {
                attribute = customAttributes['ffl_license'];
            }

            if (!_.isNull(attribute) && !_.isUndefined(attribute)) {
                if (_.isObject(attribute) && !_.isUndefined(attribute.value)) {
                    fflLicense = attribute.value;
                } else {
                    fflLicense = attribute;
                }
            }

            if (_.isNull(fflLicense) || _.isUndefined(fflLicense)) {
                fflLicense = shippingAddress.dealer_license || addressAttributes.ffl_license;
            }

            if(!_.isNull(fflLicense) && !_.isUndefined(fflLicense)) {
                payload.addressInformation.extension_attributes = _.extend(
                    payload.addressInformation.extension_attributes || {},
                    {
                        ffl_license: fflLicense,
                        ffl_dealer_data: shippingAddress.ffl_dealer_data ||
                            addressAttributes.ffl_dealer_data || null
                    }
                );
            }

            // FFL extensions belong to ShippingInformationInterface, not to
            // AddressInterface. Strip them from payload copies only; Magento
            // must retain the selected dealer metadata in its live quote.
            ['shipping_address', 'billing_address'].forEach(function (field) {
                var address = payload.addressInformation[field];
                if (!address) {
                    return;
                }
                var copy = _.extend({}, address);
                ['extensionAttributes', 'extension_attributes'].forEach(function (key) {
                    if (address[key]) {
                        copy[key] = _.extend({}, address[key]);
                        delete copy[key].ffl_license;
                        delete copy[key].ffl_dealer_data;
                    }
                });
                payload.addressInformation[field] = copy;
            });
            return payload;
        });
    };
});
