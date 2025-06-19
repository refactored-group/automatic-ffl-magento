/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'uiComponent',
    'ko',
    'Magento_Checkout/js/checkout-data',
    'RefactoredGroup_AutoFflCore/js/checkout/helper/shipping-mode'
], function ($, Component, ko, checkoutData, shippingMode) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'RefactoredGroup_AutoFflCore/cart/select-dealer-button'
        },
        // These observables will be shared across all instances of this UI Component
        currentFflItemId: ko.observable(),
        currentFullAddress: ko.observable(),
        dealerAddress: ko.observableArray(),
        dealerAddressId: ko.observableArray(),
        fflButtonLabel: null,
        /** @inheritdoc */
        initialize: function () {
            this._super();
            var self = this;

            // Observables initialized here won't be shared across other instances of this component
            this.fflButtonLabel = ko.observable(null);
            this.fflButtonLabel('Select Dealer');
            this.dealerAddress[this.dealerButtonId] = ko.observable();
            this.dealerAddressId[this.dealerButtonId] = ko.observable();

            // Update "Select Dealer" button label when a dealer is selected
            this.dealerAddressId[this.dealerButtonId].subscribe(function (value) {
                self.fflButtonLabel('Change Dealer');
            });

            self.addDealerIdToStorage(this.dealerButtonId);

            return this;
        },
        /**
         * Adds the dealer ID to the localStorage
         */
        addDealerIdToStorage: function (id) {
            if (id === undefined) return;

            if (shippingMode.isMultishipping()) {
                checkoutData.setFflQuoteLineItemId(false);
            } else {
                let fflQuoteLineItemId = new Array();
                if (checkoutData.getFflQuoteLineItemId().length) {
                    fflQuoteLineItemId = checkoutData.getFflQuoteLineItemId();
                }

                if (!fflQuoteLineItemId.includes(id)) {
                    fflQuoteLineItemId.push(id);
                }

                checkoutData.setFflQuoteLineItemId(fflQuoteLineItemId);
            }
        },
        /**
         * Open modal and set current selected item
         */
        openSelectDealerModal: function () {
            this.currentFflItemId(this.dealerButtonId);
            $("#dealers-popup").modal("openModal");
        }
    });
});
