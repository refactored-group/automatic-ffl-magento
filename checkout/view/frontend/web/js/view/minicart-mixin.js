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
             * Set the property stored in localStorage
             * to false (proceedToCheckoutWithMultipleAddresses)
             */
            proceedToCheckoutListener: function () {
                this.proceedToCheckoutButton.on('click', function () {
                    checkoutData.setProceedToCheckoutWithMultipleAddresses(false);
                });
            }
        });
    };
});