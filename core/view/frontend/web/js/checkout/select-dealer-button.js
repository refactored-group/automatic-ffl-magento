/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'RefactoredGroup_AutoFflCore/js/cart/select-dealer-button',
    'ko',
    'Magento_Customer/js/customer-data'
], function ($, Component, ko, storage) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'RefactoredGroup_AutoFflCore/checkout/select-dealer-button'
        },
        fflButtonLabel: null,
        /** @inheritdoc */
        initialize: function () {
            this._super();
            var self = this;
            var data = storage.get('checkout-data')();

            // Observables initialized here won't be shared across other instances of this component
            this.fflButtonLabel = ko.observable(null);

            // If an address has already been saved in the local storage, change the button label
            if (data['selectedShippingAddress']) {
                this.fflButtonLabel('Change Dealer');
            } else {
                this.fflButtonLabel('Find a Dealer');
            }

            // Update "Select Dealer" button label when a dealer is selected
            this.dealerAddressId[this.dealerButtonId].subscribe(function (value) {
                self.fflButtonLabel('Change Dealer');
            });

            return this;
        }
    });
});
