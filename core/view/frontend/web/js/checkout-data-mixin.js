/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'Magento_Customer/js/customer-data'
], function (
    storage
) {
    'use strict';

    return function (checkoutData) {

        var cacheKey = 'checkout-data',

            /**
             * @param {Object} data
             */
            saveData = function (data) {
                storage.set(cacheKey, data);
            },

            /**
             * @return {Object}
             */
            getData = function () {
                return storage.get(cacheKey)();
            };

        /**
         * 
         * When on the /checkout/cart/ page, this function assigns
         * the true value when the "Proceed To Checkout" button is clicked.
         * 
         * Otherwise, if the "Check Out with Multiple Addresses" is clicked,
         * sets the value to false.
         */
        checkoutData.setFflProceedToCheckoutButtonPressed = function (data) {
            var obj = getData();

            obj.fflProceedToCheckoutButtonPressed = data;
            saveData(obj);
        };
        
        /**
         * 
         * Getter function
         */
        checkoutData.isFflProceedToCheckoutButtonPressed = function () {
            return getData().fflProceedToCheckoutButtonPressed || false;
        };

        /**
         * This function stores the table row index value of FFL items.
         * 
         * When the "Select Dealer" button is clicked and after the customer selects
         * a dealer from the Google Map, inside the AJAX call an iteration
         * will occur using this array to set the address of the selected dealer.
         */
        checkoutData.setFflQuoteLineItemId = function (data) {
            var obj = getData();

            obj.fflQuoteLineItemId = data;
            saveData(obj);
        };
        
        /**
         * 
         * Getter function
         */
        checkoutData.getFflQuoteLineItemId = function () {
            return getData().fflQuoteLineItemId || false;
        };

        /**
         * Added this check to let Magento know that the button
         * has been clicked from the main shopping cart page.
         */
        checkoutData.setFromCheckoutPage = function (data) {
            var obj = getData();

            obj.fromCheckoutPage = data;
            saveData(obj);
        };

        /**
         * 
         * Getter function
         */
        checkoutData.isFromCheckoutPage = function () {
            return getData().fromCheckoutPage || false;
        };

        return checkoutData;
    };
});