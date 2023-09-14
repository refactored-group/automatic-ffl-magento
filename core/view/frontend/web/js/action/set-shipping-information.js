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

            if (shippingAddress['extension_attributes'] === undefined) {
                shippingAddress['extension_attributes'] = {};
            }

            var attribute = shippingAddress.customAttributes.find(
                function (element) {
                    return element.attribute_code === 'ffl_license';
                }
            );

            if(!_.isUndefined(attribute) && !_.isUndefined(attribute.value)) {
                payload.addressInformation.extension_attributes = _.extend(
                    payload.addressInformation.extension_attributes || {},
                    {
                        ffl_license: attribute.value
                    }
                );
            }
            return payload;
        });
    };
});
