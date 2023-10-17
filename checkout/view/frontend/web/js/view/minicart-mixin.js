/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'uiComponent',
    'jquery',
    'Magento_Checkout/js/checkout-data'
], function (Component, $, checkoutData) {
    'use strict';

    return function (Component) {
        return Component.extend({
            initialize: function () {
                this._super();
                this.proceedToCheckoutButton = $('[data-block="minicart"]');
                this.proceedToCheckoutListener();

                return this;
            },
            /**
             * Add an onClick event listener to the
             * "Proceed To Checkout" button found in
             * the minicart dropdown menu.
             */
            proceedToCheckoutListener: function () {
                this.proceedToCheckoutButton.on('click', function () {
                    checkoutData.setFromCheckoutPage(true);
                    checkoutData.setFflQuoteLineItemId(false);
                    checkoutData.setFflProceedToCheckoutButtonPressed(true);
                });
            }
        });
    };
});