/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */

define([
    'jquery',
    'Magento_Checkout/js/checkout-data'
], function ($, checkoutData) {
    'use strict';

    return function (config, element) {
        /**
         * If the "Check Out with Multiple Addresses" button link is clicked,
         * set the fflProceedToCheckoutButtonPressed value to false.
         * 
         * Reset the value of the array holding the row indices containing the
         * dealer's address ID to false.
         */
        $(element).on('click', function (event) {
            checkoutData.setFromCheckoutPage(true);
            checkoutData.setFflQuoteLineItemId(false);
            checkoutData.setFflProceedToCheckoutButtonPressed(false);
        });

    };
});
