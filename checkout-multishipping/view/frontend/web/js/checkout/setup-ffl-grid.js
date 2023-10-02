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
        if (!checkoutData.isFflProceedToCheckoutButtonPressed()) return;

        /**
         * If the "Proceed To Checkout" button is clicked,
         * add a className to the table element so we could
         * control the styling of its children elements.
         */
        $(element).addClass('ffl-grid');
    };
});
