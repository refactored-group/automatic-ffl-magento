/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'Magento_Customer/js/customer-data',
    'RefactoredGroup_AutoFflCore/js/checkout/helper/ffl-address'
], function (
    storage,
    fflAddress
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
            },

            /**
             * Remove only the current store's persisted address when checkout data
             * uses Magento's store-scoped address format.
             *
             * @param {Object|null} storedAddress
             * @return {Object|null}
             */
            clearStoredAddress = function (storedAddress) {
                var storeCode = window.checkoutConfig && window.checkoutConfig.storeCode;

                if (storedAddress &&
                    storeCode &&
                    Object.prototype.hasOwnProperty.call(storedAddress, storeCode)
                ) {
                    delete storedAddress[storeCode];

                    return storedAddress;
                }

                return null;
            },

            /**
             * Clear dealer-derived billing data without touching a buyer's address.
             *
             * @param {Object} data
             */
            clearDealerBillingData = function (data) {
                var hasDealerBillingAddress =
                    fflAddress.isDealerAddress(data.billingAddressFromData) ||
                    fflAddress.isDealerAddress(data.newCustomerBillingAddress);

                if (fflAddress.isDealerAddress(data.billingAddressFromData)) {
                    data.billingAddressFromData = null;
                }

                if (fflAddress.isDealerAddress(data.newCustomerBillingAddress)) {
                    data.newCustomerBillingAddress = null;
                }

                if (hasDealerBillingAddress &&
                    (data.selectedBillingAddress === 'new-customer-address' ||
                        data.selectedBillingAddress === 'new-customer-billing-address')
                ) {
                    data.selectedBillingAddress = null;
                }
            };

        /**
         * Remove stale AutoFFL dealer state before Magento resolves the shipping
         * address for a cart that no longer contains an FFL item.
         *
         * @return {Boolean}
         */
        checkoutData.clearStaleFflCheckoutData = function () {
            var config = window.checkoutConfig,
                customerData = config && config.customerData,
                shippingAddressFromData,
                newCustomerShippingAddress,
                configShippingAddress,
                data;

            if (!customerData ||
                !Object.prototype.hasOwnProperty.call(customerData, 'is_ffl') ||
                (customerData.is_ffl !== 0 && customerData.is_ffl !== '0')
            ) {
                return false;
            }

            shippingAddressFromData = checkoutData.getShippingAddressFromData();
            newCustomerShippingAddress = checkoutData.getNewCustomerShippingAddress();
            configShippingAddress = config.shippingAddressFromData;

            if (!fflAddress.isDealerAddress(shippingAddressFromData) &&
                !fflAddress.isDealerAddress(newCustomerShippingAddress) &&
                !fflAddress.isDealerAddress(configShippingAddress)
            ) {
                return false;
            }

            data = getData() || {};

            if (fflAddress.isDealerAddress(shippingAddressFromData)) {
                data.shippingAddressFromData = clearStoredAddress(data.shippingAddressFromData);
            }

            if (fflAddress.isDealerAddress(newCustomerShippingAddress)) {
                data.newCustomerShippingAddress = clearStoredAddress(data.newCustomerShippingAddress);
            }

            if (data.selectedShippingAddress === 'new-customer-address') {
                data.selectedShippingAddress = null;
            }

            data.selectedShippingRate = null;
            data.selectedShippingMethod = null;
            data.fflQuoteLineItemId = false;
            data.dealer_license = null;
            data.ffl_license = null;
            clearDealerBillingData(data);
            saveData(data);

            if (fflAddress.isDealerAddress(configShippingAddress)) {
                config.shippingAddressFromData = null;
            }
            config.selectedShippingMethod = null;

            return true;
        };

        checkoutData.clearStaleFflCheckoutData();

        /**
         * This function stores the table row index value of FFL items.
         * 
         * When the "Select Dealer" button is clicked and after the customer selects
         * a dealer from the Google Map, inside the AJAX call an iteration
         * will occur using this array to set the address of the selected dealer.
         */
        checkoutData.setFflQuoteLineItemId = function (data) {
            var obj = getData() || {};

            obj.fflQuoteLineItemId = data;
            saveData(obj);
        };
        
        /**
         * 
         * Getter function
         */
        checkoutData.getFflQuoteLineItemId = function () {
            var data = getData() || {};

            return data.fflQuoteLineItemId || false;
        };

        return checkoutData;
    };
});
