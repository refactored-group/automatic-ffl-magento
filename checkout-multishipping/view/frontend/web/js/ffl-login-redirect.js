/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */

define([
    'Magento_Checkout/js/checkout-data'
], function (checkoutData) {
    'use strict';

    /**
     * The function checks if the customer comes from
     * any page except the shopping cart page (e.g. from login page),
     * it will check if the shopping cart contains FFL items.
     * 
     * If true, show only one shipping address option for
     * both FFL and non-FFL items.
     */
    return function (config, element) {
        if (!checkoutData.isFromCheckoutPage()) {
            checkoutData.setFflProceedToCheckoutButtonPressed(
                config.hasFflItem ? true : false
            );
        }
    };
});