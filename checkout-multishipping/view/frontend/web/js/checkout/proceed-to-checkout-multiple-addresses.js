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
         * If the "Check Out with Multiple Addresses" link is clicked,
         * set the proceedToCheckoutWithMultipleAddresses value to true.
         */
        $(element).on('click', function (event) {
            checkoutData.setProceedToCheckoutWithMultipleAddresses(true);
        });

    };
});
