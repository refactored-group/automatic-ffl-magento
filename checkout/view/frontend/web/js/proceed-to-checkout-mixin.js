/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/checkout-data'
], function ($, wrapper, checkoutData) {
    'use strict';

    return function (proceedToCheckout) {
        return wrapper.wrap(proceedToCheckout, function (originalAction, config, element) {
            originalAction(config, element)
            /**
             * Set the property stored in localStorage
             * to false (proceedToCheckoutWithMultipleAddresses)
             */
            $(element).on('click', function () {
                checkoutData.setProceedToCheckoutWithMultipleAddresses(false);
            });
        });
    };
});