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
    'RefactoredGroup_AutoFflCore/js/checkout/helper/ffl-address',
], function ($, Component, checkoutData, createShippingAddress, selectShippingAddress, ko, dealerButton, storage, fflAddress) {
    'use strict';

    return Component.extend({
        fflButtonLabel: ko.observable(),
        /** @inheritdoc */
        initialize: function () {
            this._super();
            this.regionJson = JSON.parse(this.regionJson);

            if (checkoutConfig.customerData.is_ffl == 1) {
                this.clearFflCheckoutData();

                // Hide manual shipping-address controls from FFL checkout.
                // @TODO: find a better way of doing this
                var styleTag = $(
                    '<style>' +
                    '.checkout-shipping-address .new-address-popup,' +
                    '#shipping-new-address-form,' +
                    '.checkout-shipping-address .edit-address-link { display: none !important; }' +
                    '</style>'
                )
                $('html > head').append(styleTag);
            } else {
                var shippingAddress = checkoutData.getNewCustomerShippingAddress();

                if (shippingAddress && shippingAddress.hasOwnProperty('is_ffl') && shippingAddress.is_ffl === 1) {
                    //Clear previous dealer shipping address when no FFL item is detected
                    var data = storage.get('checkout-data')() || {};
                    data['shippingAddressFromData'] = null;
                    data['newCustomerShippingAddress'] = null;
                    data['selectedShippingAddress'] = null;

                    this.saveCheckoutData(data);
                }
            }

            return this;
        },
        /**
         * FFL checkout must start from a fresh dealer choice on every full page load.
         */
        clearFflCheckoutData: function () {
            var data = storage.get('checkout-data')() || {};

            data['shippingAddressFromData'] = null;
            data['newCustomerShippingAddress'] = null;
            data['selectedShippingAddress'] = null;
            data['selectedShippingRate'] = null;
            data['selectedShippingMethod'] = null;
            data['fflQuoteLineItemId'] = false;
            data['dealer_license'] = null;
            data['ffl_license'] = null;
            this.clearFflBillingData(data);

            this.saveCheckoutData(data);
        },
        clearFflBillingData: function (data) {
            var hasDealerBillingAddress =
                fflAddress.isDealerAddress(data['billingAddressFromData']) ||
                fflAddress.isDealerAddress(data['newCustomerBillingAddress']);

            if (fflAddress.isDealerAddress(data['billingAddressFromData'])) {
                data['billingAddressFromData'] = null;
            }

            if (fflAddress.isDealerAddress(data['newCustomerBillingAddress'])) {
                data['newCustomerBillingAddress'] = null;
            }

            if (hasDealerBillingAddress && data['selectedBillingAddress'] === 'new-customer-address') {
                data['selectedBillingAddress'] = null;
            }
        },
        /**
         * @param {Object} data
         */
        saveCheckoutData: function (data) {
            storage.set('checkout-data', data);
            window.localStorage.setItem('checkout-data', JSON.stringify(data));
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
         * Returns phone number in the format (xxx)-xxx-xxxx
         *
         * @param phoneNumberString
         * @returns {string|null}
         */
        formatPhoneNumber: function (phoneNumberString) {
            const cleaned = ('' + phoneNumberString).replace(/\D/g, '');
            const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
            if (match) {
                return '(' + match[1] + ')' + match[2] + '-' + match[3];
            }
            return null;
        },
        /**
         *
         * @param dealerId
         */
        selectDealer: function (dealerId) {
            var self = this;
            var dealer = this.fflResults()[dealerId]
            var region = this.getRegionData(dealer.premise_state);
            var addressData = {
                city: dealer.premise_city,
                company: dealer.business_name,
                country_id: "US",
                firstname: this.default_firstname,
                lastname: this.default_lastname,
                dealer_license: dealer.license,
                custom_attributes: {
                    ffl_license: dealer.license
                },
                extension_attributes: {
                    ffl_license: dealer.license
                },
                postcode: dealer.premise_zip,
                region: region.name,
                region_id: region.id,
                is_ffl: 1,
                street: {
                    0: dealer.premise_street,
                },
                telephone: self.formatPhoneNumber(dealer.phone_number),
                telephone_link: 'tel:+1' + dealer.phone_number,
                save_in_address_book: 0
            };
            
            checkoutData.setShippingAddressFromData(addressData);

            // New address must be selected as a shipping address
            var newShippingAddress = createShippingAddress(addressData);
            selectShippingAddress(newShippingAddress);
            checkoutData.setNewCustomerShippingAddress($.extend(true, {}, addressData));

            // Set new shipping address as the selected address
            var storageData = storage.get('checkout-data')() || {};
            storageData['selectedShippingAddress'] = newShippingAddress.getKey();
            this.clearFflBillingData(storageData);
            this.saveCheckoutData(storageData);

            $("#dealers-popup").modal("closeModal");
            dealerButton().dealerAddressId[self.currentFflItemId()]('1');

            /**
             * Set default values to the form in order to avoid validation errors.
             */
            if ($('#shipping-new-address-form')) {
                $('#shipping-new-address-form input[name=firstname]').val(addressData['firstname']).trigger('change');
                $('#shipping-new-address-form input[name=lastname]').val(addressData['lastname']).trigger('change');
                $('#shipping-new-address-form input[name=company]').val(addressData['company']).trigger('change');
                $('#shipping-new-address-form input[name=\'street[0]\']').val(addressData['street'][0]).trigger('change');
                $('#shipping-new-address-form select[name=country_id] option[value=US]').attr('selected', 'selected').trigger('change');
                $('#shipping-new-address-form select[name=region_id] option[value=' + addressData['region_id'] + ']').prop('selected', true).trigger('change');
                $('#shipping-new-address-form input[name=city]').val(addressData['city']).trigger('change');
                $('#shipping-new-address-form input[name=postcode]').val(addressData['postcode']).trigger('change');
                $('#shipping-new-address-form input[name=telephone]').val(addressData['telephone']).trigger('change');
                $('#shipping-new-address-form input[name=custom_attributes\\[ffl_license\\]]').val(addressData['dealer_license']).trigger('change');
            }
        }
    });
});
