/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'RefactoredGroup_AutoFflCore/js/cart/dealers-popup',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/action/create-shipping-address',
    'Magento_Checkout/js/action/select-shipping-address',
    'ko',
    'RefactoredGroup_AutoFflCore/js/checkout/select-dealer-button',
    'Magento_Customer/js/customer-data',
    'uiRegistry'
], function ($, Component, checkoutData, createShippingAddress, selectShippingAddress, ko, dealerButton, storage, registry) {
    'use strict';

    return Component.extend({
        fflButtonLabel: ko.observable(),
        /** @inheritdoc */
        initialize: function () {
            this._super();
            this.regionJson = JSON.parse(this.regionJson);

            // checkout-data clears stale addresses before native resolution.
            // Repeating the reset here would erase the hydrated shopper form.
            return this;
        },
        /**
         * @param {Object} data
         */
        saveCheckoutData: function (data) {
            storage.set('checkout-data', data);
            try {
                window.localStorage.setItem('checkout-data', JSON.stringify(data));
            } catch (error) {
                // Magento customer-data remains the canonical saved copy.
            }
        },
        /**
         *
         */
        getRegionData: function (region) {
            for (const [key, regionObject] of Object.entries(this.regionJson['US'])) {
                if (regionObject.code === region) {
                    return {id: key, name: regionObject.name};
                }
            }
        },
        /**
         * Apply the validated shared-map payload through Magento's native address actions.
         */
        applySelectedDealer: function (dealer) {
            var self = this;
            var region = this.getRegionData(dealer.state);
            if (!region) {
                this.selectionError('The selected dealer state is unavailable. Please try another dealer.');
                this.applyingSelection = false;
                return;
            }
            registry.async('checkoutProvider')(function (provider) {
                var recipient = provider.get('shippingAddress') || {};
                var addressData = {
                    city: dealer.city,
                    company: dealer.company,
                    country_id: "US",
                    firstname: recipient.firstname !== undefined ? recipient.firstname : self.default_firstname,
                    lastname: recipient.lastname !== undefined ? recipient.lastname : self.default_lastname,
                    dealer_license: dealer.license,
                    ffl_dealer_data: JSON.stringify(dealer),
                    custom_attributes: {
                        ffl_license: dealer.license
                    },
                    extension_attributes: {
                        ffl_license: dealer.license,
                        ffl_dealer_data: JSON.stringify(dealer)
                    },
                    postcode: dealer.postalCode,
                    region: region.name,
                    region_id: region.id,
                    region_code: dealer.state,
                    is_ffl: 1,
                    // checkoutProvider emits nested field updates for objects.
                    // Arrays leave the native street inputs stale or empty.
                    street: { '0': dealer.address1, '1': dealer.address2 || '' },
                    telephone: dealer.phone,
                    telephone_link: 'tel:' + dealer.phone.replace(/[^+\d]/g, ''),
                    save_in_address_book: 0
                };

                checkoutData.setShippingAddressFromData(addressData);

                // New address must be selected as a shipping address
                var newShippingAddress = createShippingAddress(addressData);
                selectShippingAddress(newShippingAddress);
                checkoutData.setNewCustomerShippingAddress($.extend(true, {}, addressData));
                checkoutData.setFflDealerAddressIdentity(addressData);

                // Set new shipping address as the selected address
                var storageData = storage.get('checkout-data')() || {};
                storageData['selectedShippingAddress'] = newShippingAddress.getKey();
                self.saveCheckoutData(storageData);

                $("#dealers-popup").modal("closeModal");
                self.modalActive = false;
                self.applyingSelection = false;
                dealerButton().dealerAddressId[self.currentFflItemId()](dealer.id);

                // Keep native form validation and rate synchronization on the same
                // complete address, including the dealer metadata. Updating DOM
                // fields individually can produce a partial non-dealer quote.
                provider.set('shippingAddress', $.extend(true, {}, addressData));
            });
        }
    });
});
