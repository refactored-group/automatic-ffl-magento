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

            if (shippingAddress['extension_attributes'] === undefined) {
                shippingAddress['extension_attributes'] = {};
            }

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
                fflLicense = shippingAddress.dealer_license;
            }

            if(!_.isNull(fflLicense) && !_.isUndefined(fflLicense)) {
                payload.addressInformation.extension_attributes = _.extend(
                    payload.addressInformation.extension_attributes || {},
                    {
                        ffl_license: fflLicense
                    }
                );
            }
            return payload;
        });
    };
});
