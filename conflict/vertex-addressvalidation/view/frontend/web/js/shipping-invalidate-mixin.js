/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'uiRegistry',
    'mage/utils/wrapper'
], function (registry, wrapper) {
    'use strict';

    let config = {};
    if (window.checkoutConfig && window.checkoutConfig.vertexAddressValidationConfig) {
        config = window.checkoutConfig.vertexAddressValidationConfig;
    }

    return function (target) {
        if (!config.isAddressValidationEnabled) {
            return target;
        }

        const validationMessage = registry.get(
            'checkout.steps.shipping-step.shippingAddress' +
            '.before-shipping-method-form.shippingAdditional'
        );

        target.setSelectedShippingAddress = wrapper.wrap(target.setSelectedShippingAddress, function (original, args) {
            const addressValidator = registry.get(
                'checkout.steps.shipping-step.shippingAddress' +
                '.before-shipping-method-form.shippingAdditional' +
                '.address-validation-message.validator'
            );

            addressValidator.isAddressValid = false;
            validationMessage.clear();

            return original(args);
        });

        return target;
    }
});