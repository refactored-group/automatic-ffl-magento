/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'RefactoredGroup_AutoFflCore/js/cart/select-dealer-button',
    'ko',
    'Magento_Customer/js/customer-data',
    'RefactoredGroup_AutoFflCore/js/checkout/destination',
    'uiRegistry',
    'mage/translate'
], function ($, Component, ko, storage, destination, registry, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'RefactoredGroup_AutoFflCore/checkout/select-dealer-button'
        },
        fflButtonLabel: null,
        /** @inheritdoc */
        initialize: function () {
            this._super();
            this.requiresDealer = destination.requiresDealer;
            this.recipientValidationMessage = ko.observable('');
            var self = this;
            var data = storage.get('checkout-data')();

            // Observables initialized here won't be shared across other instances of this component
            this.fflButtonLabel = ko.observable(null);

            // FFL checkout requires a fresh dealer selection on every full page load.
            if (this.is_ffl) {
                this.fflButtonLabel('Find a Dealer');
            } else if (data['selectedShippingAddress']) {
                this.fflButtonLabel('Change Dealer');
            } else {
                this.fflButtonLabel('Find a Dealer');
            }

            // Update "Select Dealer" button label when a dealer is selected
            this.dealerAddressId[this.dealerButtonId].subscribe(function (value) {
                self.fflButtonLabel(value ? 'Change Dealer' : 'Find a Dealer');
            });
            this.destinationSubscription = destination.revision.subscribe(function () {
                self.dealerAddressId[self.dealerButtonId](null);
            });

            return this;
        },

        openSelectDealerModal: function () {
            var self = this;
            var proceed = this._super.bind(this);
            registry.async('checkoutProvider')(function (provider) {
                var data = provider.get('shippingAddress') || {};
                var first = String(data.firstname || '').trim();
                var last = String(data.lastname || '').trim();
                if (self.requiresDealer() && (!first || !last ||
                    (first.toLowerCase() === 'ffl' && last.toLowerCase() === 'dealer'))) {
                    ['firstname', 'lastname'].forEach(function (field) {
                        var input = registry.get(self.parentName + '.shipping-address-fieldset.' + field);
                        if (input) {
                            input.validate();
                        }
                    });
                    self.recipientValidationMessage($t('Enter the recipient first and last name before choosing a dealer.'));
                    return;
                }
                self.recipientValidationMessage('');
                proceed();
            });
        },

        destroy: function () {
            this.destinationSubscription.dispose();
            return this._super();
        }
    });
});
