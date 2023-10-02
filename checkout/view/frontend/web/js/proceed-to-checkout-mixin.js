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
             * If the "Proceed To Checkout" button is clicked,
             * set the fflProceedToCheckoutButtonPressed value to true.
             * 
             * Reset the value of the array holding the row indices containing the
             * dealer's address ID to false.
             */
            $(element).on('click', function () {
                checkoutData.setFflQuoteLineItemId(false);
                checkoutData.setFflProceedToCheckoutButtonPressed(true);
            });
        });
    };
});