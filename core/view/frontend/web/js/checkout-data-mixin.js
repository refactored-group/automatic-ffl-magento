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
         * Set if "Check Out with Multiple Addresses" link is clicked
         */
        checkoutData.setProceedToCheckoutWithMultipleAddresses = function (data) {
            var obj = getData();

            obj.proceedToCheckoutWithMultipleAddresses = data;
            saveData(obj);
        };

        /**
         * 
         * Getter function
         */
        checkoutData.isProceedToCheckoutWithMultipleAddresses = function () {
            return getData().proceedToCheckoutWithMultipleAddresses || false;
        };

        return checkoutData;
    };
});