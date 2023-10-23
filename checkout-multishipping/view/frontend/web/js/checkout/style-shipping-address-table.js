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
         * If "Check Out with Multiple Addresses" is clicked, ignore
         */
        if (checkoutData.isProceedToCheckoutWithMultipleAddresses()) {
            return;
        }

        /**
         * Add new class name to enable custom styling
         */
        $(element).addClass('ffl-shipping-address-table');
    };
});
