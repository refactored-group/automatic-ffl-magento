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

            isAddressLike = function (value) {
                return Boolean(
                    value &&
                    (
                        fflAddress.getAddressValue(value, 'firstname') ||
                        fflAddress.getAddressValue(value, 'lastname') ||
                        fflAddress.getAddressValue(value, 'company') ||
                        fflAddress.getAddressValue(value, 'telephone') ||
                        fflAddress.getAddressValue(value, 'street')
                    )
                );
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

                    return Object.keys(storedAddress).length ? storedAddress : null;
                }

                if (isAddressLike(storedAddress)) {
                    return null;
                }

                return storedAddress && Object.keys(storedAddress).length ? storedAddress : null;
            },

            /**
             * Return the address stored for the current store, or the address
             * itself when Magento is using the unscoped checkout-data shape.
             *
             * @param {Object|null} storedAddress
             * @return {Object|null}
             */
            getStoredAddress = function (storedAddress) {
                var storeCode = window.checkoutConfig && window.checkoutConfig.storeCode;

                if (storedAddress &&
                    storeCode &&
                    Object.prototype.hasOwnProperty.call(storedAddress, storeCode)
                ) {
                    return storedAddress[storeCode];
                }

                return isAddressLike(storedAddress) ? storedAddress : null;
            },

            getStoredDealerIdentity = function (storedIdentity) {
                var storeCode = window.checkoutConfig && window.checkoutConfig.storeCode;

                if (!storedIdentity) {
                    return null;
                }

                if (storeCode &&
                    Object.prototype.hasOwnProperty.call(storedIdentity, storeCode)
                ) {
                    return storedIdentity[storeCode];
                }

                if (fflAddress.getAddressValue(storedIdentity, 'firstname') ||
                    fflAddress.getAddressValue(storedIdentity, 'lastname') ||
                    fflAddress.getAddressValue(storedIdentity, 'company') ||
                    fflAddress.getAddressValue(storedIdentity, 'telephone')
                ) {
                    return storedIdentity;
                }

                return null;
            },

            clearStoredDealerIdentity = function (storedIdentity) {
                var storeCode = window.checkoutConfig && window.checkoutConfig.storeCode;

                if (storedIdentity &&
                    storeCode &&
                    Object.prototype.hasOwnProperty.call(storedIdentity, storeCode)
                ) {
                    delete storedIdentity[storeCode];

                    return Object.keys(storedIdentity).length ? storedIdentity : null;
                }

                if (storedIdentity &&
                    !fflAddress.getAddressValue(storedIdentity, 'firstname') &&
                    !fflAddress.getAddressValue(storedIdentity, 'lastname') &&
                    !fflAddress.getAddressValue(storedIdentity, 'company') &&
                    !fflAddress.getAddressValue(storedIdentity, 'telephone')
                ) {
                    return storedIdentity;
                }

                return null;
            },

            /**
             * Clear dealer-derived billing data without touching a buyer's address.
             *
             * @param {Object} data
             * @param {Object|null} dealerIdentity
             */
            clearDealerBillingData = function (data, dealerIdentity) {
                var billingAddressFromData = getStoredAddress(data.billingAddressFromData),
                    newCustomerBillingAddress = getStoredAddress(data.newCustomerBillingAddress),
                    hasDealerBillingAddress =
                        fflAddress.isDealerDerivedAddress(billingAddressFromData, dealerIdentity) ||
                        fflAddress.isDealerDerivedAddress(newCustomerBillingAddress, dealerIdentity);

                if (fflAddress.isDealerDerivedAddress(billingAddressFromData, dealerIdentity)) {
                    data.billingAddressFromData = clearStoredAddress(data.billingAddressFromData);
                }

                if (fflAddress.isDealerDerivedAddress(newCustomerBillingAddress, dealerIdentity)) {
                    data.newCustomerBillingAddress = clearStoredAddress(data.newCustomerBillingAddress);
                }

                if (hasDealerBillingAddress &&
                    (data.selectedBillingAddress === 'new-customer-address' ||
                        data.selectedBillingAddress === 'new-customer-billing-address')
                ) {
                    data.selectedBillingAddress = null;
                }
            };

        /**
         * Reset the previous dealer before Magento resolves an FFL checkout, or
         * remove only a matched stale dealer from a non-FFL checkout.
         *
         * @return {Boolean}
         */
        checkoutData.clearStaleFflCheckoutData = function () {
            var config = window.checkoutConfig,
                customerData = config && config.customerData,
                isFflCart,
                isNonFflCart,
                shippingAddressFromData,
                newCustomerShippingAddress,
                configShippingAddress,
                configBillingAddress,
                dealerIdentity,
                hasDealerProvenance,
                shippingAddressFromDataIsDealer,
                newCustomerShippingAddressIsDealer,
                configShippingAddressIsDealer,
                hasDealerShippingAddress,
                data;

            if (!customerData ||
                !Object.prototype.hasOwnProperty.call(customerData, 'is_ffl')
            ) {
                return false;
            }

            isFflCart = customerData.is_ffl === 1 || customerData.is_ffl === '1';
            isNonFflCart = customerData.is_ffl === 0 || customerData.is_ffl === '0';

            if (!isFflCart && !isNonFflCart) {
                return false;
            }

            shippingAddressFromData = checkoutData.getShippingAddressFromData();
            newCustomerShippingAddress = checkoutData.getNewCustomerShippingAddress();
            configShippingAddress = config.shippingAddressFromData;
            configBillingAddress = config.billingAddressFromData;
            data = getData() || {};
            dealerIdentity = getStoredDealerIdentity(data.fflDealerAddressIdentity);
            hasDealerProvenance = Boolean(dealerIdentity);
            shippingAddressFromDataIsDealer =
                fflAddress.isDealerDerivedAddress(shippingAddressFromData, dealerIdentity);
            newCustomerShippingAddressIsDealer =
                fflAddress.isDealerDerivedAddress(newCustomerShippingAddress, dealerIdentity);
            configShippingAddressIsDealer =
                fflAddress.isDealerDerivedAddress(configShippingAddress, dealerIdentity);
            hasDealerShippingAddress =
                shippingAddressFromDataIsDealer ||
                newCustomerShippingAddressIsDealer ||
                configShippingAddressIsDealer;

            if (isNonFflCart &&
                !hasDealerShippingAddress &&
                !fflAddress.isDealerDerivedAddress(configBillingAddress, dealerIdentity) &&
                !hasDealerProvenance
            ) {
                return false;
            }

            if (isFflCart || shippingAddressFromDataIsDealer) {
                data.shippingAddressFromData = clearStoredAddress(data.shippingAddressFromData);
            }

            if (isFflCart || newCustomerShippingAddressIsDealer) {
                data.newCustomerShippingAddress = clearStoredAddress(data.newCustomerShippingAddress);
            }

            if (isFflCart ||
                (hasDealerShippingAddress &&
                    data.selectedShippingAddress === 'new-customer-address')
            ) {
                data.selectedShippingAddress = null;
            }

            if (isFflCart || hasDealerShippingAddress) {
                data.selectedShippingRate = null;
                data.selectedShippingMethod = null;
            }

            data.fflQuoteLineItemId = false;
            data.dealer_license = null;
            data.ffl_license = null;
            data.fflDealerAddressIdentity =
                clearStoredDealerIdentity(data.fflDealerAddressIdentity);
            clearDealerBillingData(data, dealerIdentity);
            saveData(data);

            if (isFflCart || configShippingAddressIsDealer) {
                config.shippingAddressFromData = null;
            }

            if (fflAddress.isDealerDerivedAddress(configBillingAddress, dealerIdentity)) {
                config.billingAddressFromData = null;
            }

            if (isFflCart || hasDealerShippingAddress) {
                config.selectedShippingMethod = null;
            }

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
