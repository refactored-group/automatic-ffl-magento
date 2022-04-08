/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'Razoyo_AutoFflCore/js/cart/dealers-popup',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/action/create-shipping-address',
    'Magento_Checkout/js/action/select-shipping-address',
    'uiRegistry'
], function ($, Component, checkoutData, createShippingAddress, selectShippingAddress, uiRegistry) {
    'use strict';

    return Component.extend({
        /** @inheritdoc */
        initialize: function () {
            this._super();

            this.regionJson = JSON.parse(this.regionJson);

            // Hide Create New Address form and edit address link from Checkout
            // @TODO: find a better way of doing this
            var styleTag = $('<style>#shipping-new-address-form, .shipping-new-address-form { display: none !important; }</style>')
            $('html > head').append(styleTag);

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
         *
         * @param dealerId
         */
        selectDealer: function (dealerId) {
            var dealer = this.fflResults()[dealerId]
            var region = this.getRegionData(dealer.premise_state);
            var addressData = {
                city: dealer.premise_city,
                company: "",
                country_id: "US",
                firstname: dealer.business_name,
                lastname: ".",
                postcode: dealer.premise_zip,
                region: region.name,
                region_id: region.id,
                street: {
                    0: dealer.premise_street,
                },
                telephone: dealer.phone_number,
                save_in_address_book: 0
            };
            checkoutData.setShippingAddressFromData(addressData);

            // New address must be selected as a shipping address
            var newShippingAddress = createShippingAddress(addressData);
            selectShippingAddress(newShippingAddress);
            checkoutData.setSelectedShippingAddress(newShippingAddress.getKey());
            checkoutData.setNewCustomerShippingAddress($.extend(true, {}, addressData));

            $("#dealers-popup").modal("closeModal");

            return;
        }
    });
});
