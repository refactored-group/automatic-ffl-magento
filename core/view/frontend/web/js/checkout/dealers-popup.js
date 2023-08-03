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
], function ($, Component, checkoutData, createShippingAddress, selectShippingAddress, ko, dealerButton, storage) {
    'use strict';

    return Component.extend({
        fflButtonLabel: ko.observable(),
        /** @inheritdoc */
        initialize: function () {
            this._super();
            this.regionJson = JSON.parse(this.regionJson);

            if (checkoutConfig.customerData.is_ffl == 1) {
                // Hide Create New Address form and edit address link from Checkout
                // @TODO: find a better way of doing this
                var styleTag = $('<style>#shipping-new-address-form, .edit-address-link { display: none !important; }</style>')
                $('html > head').append(styleTag);
            } else {
                var shippingAddress = checkoutData.getNewCustomerShippingAddress();

                if (shippingAddress && shippingAddress.hasOwnProperty('is_ffl') && shippingAddress.is_ffl === 1) {
                    //Clear previous dealer shipping address when no FFL item is detected
                    var data = storage.get('checkout-data')();
                    data['shippingAddressFromData'] = null;
                    data['newCustomerShippingAddress'] = null;
                    data['selectedShippingAddress'] = null;

                    window.localStorage.setItem('checkout-data', JSON.stringify(data));
                }
            }

            return this;
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

            let date = new Date();

            date.setDate(date.getDate() + 1);
            
            document.cookie = 'FFL_Dealer_Id=' + addressData.dealer_license + '; expires=' + date.toGMTString() + '; path=/';

            checkoutData.setShippingAddressFromData(addressData);

            // New address must be selected as a shipping address
            var newShippingAddress = createShippingAddress(addressData);
            selectShippingAddress(newShippingAddress);
            checkoutData.setNewCustomerShippingAddress($.extend(true, {}, addressData));

            // Set new shipping address as the selected address
            var storageData = storage.get('checkout-data')();
            storageData['selectedShippingAddress'] = newShippingAddress.getKey();
            window.localStorage.setItem('checkout-data', JSON.stringify(storageData));

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
            }
        }
    });
});
