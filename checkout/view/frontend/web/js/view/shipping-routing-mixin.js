define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/address-converter',
    'RefactoredGroup_AutoFflCore/js/checkout/routing',
    'mage/translate'
], function (quote, checkoutData, converter, routing, $t) {
    'use strict';

    return function (Component) {
        return Component.extend({
            initialize: function () {
                this._super();
                this.autofflChecking = false;
                this.autofflSequence = 0;
                this.autofflTimer = null;
                this.autofflConfig = window.checkoutConfig.autofflRouting || {};
                if (this.autofflConfig.enabled && this.autofflConfig.ammoOnly) {
                    this.autofflSubscription = quote.shippingAddress.subscribe(this.checkAmmoOnlyDestination.bind(this));
                    this.checkAmmoOnlyDestination(quote.shippingAddress());
                }
                return this;
            },

            destinationKey: function (address) {
                return address ? [address.countryId, address.regionCode, address.regionId].join('|') : '';
            },

            rememberCustomerAddress: function (address) {
                if (routing.isDealer(address)) {
                    return;
                }
                var data = converter.quoteAddressToFormAddressData(address);
                checkoutData.setShippingAddressFromData(data);
                // Dealer delivery must not replace the shopper's billing address.
                if (!quote.billingAddress() && !checkoutData.getBillingAddressFromData()) {
                    checkoutData.setBillingAddressFromData(data);
                }
            },

            checkAmmoOnlyDestination: function (address) {
                var self = this;
                var key = this.destinationKey(address);
                if (!address || routing.isDealer(address) || (!address.regionCode && !address.regionId) ||
                    key === this.autofflDestinationKey) {
                    return;
                }
                this.autofflDestinationKey = key;
                var sequence = ++this.autofflSequence;
                clearTimeout(this.autofflTimer);
                this.autofflTimer = setTimeout(function () {
                    routing.check(self.autofflConfig, address).done(function (result) {
                        if (sequence !== self.autofflSequence || key !== self.destinationKey(quote.shippingAddress())) {
                            return;
                        }
                        if (result.requiresDealer !== !!window.checkoutConfig.customerData.is_ffl) {
                            self.rememberCustomerAddress(address);
                            routing.continueTo(result);
                        }
                    }).fail(function () {
                        self.autofflDestinationKey = null;
                        self.errorValidationMessage($t('Ammunition shipping could not be checked. Please try again.'));
                    });
                }, 400);
            },

            setShippingInformation: function () {
                var self = this;
                var proceed = this._super.bind(this);
                var address = quote.shippingAddress();
                if (!this.autofflConfig.enabled || routing.isDealer(address)) {
                    return proceed();
                }
                if (this.autofflChecking || !this.validateShippingInformation()) {
                    return false;
                }
                address = quote.shippingAddress();
                var key = this.destinationKey(address);
                this.autofflChecking = true;
                ++this.autofflSequence;
                clearTimeout(this.autofflTimer);
                return routing.check(this.autofflConfig, address).done(function (result) {
                    if (key !== self.destinationKey(quote.shippingAddress())) {
                        self.errorValidationMessage($t('Your delivery address changed. Please continue again.'));
                        return;
                    }
                    self.rememberCustomerAddress(address);
                    if (result.route === 'standard' && result.requiresDealer === !!window.checkoutConfig.customerData.is_ffl) {
                        proceed();
                    } else {
                        routing.continueTo(result);
                    }
                }).fail(function (response) {
                    self.errorValidationMessage(response.responseJSON && response.responseJSON.error ||
                        $t('Ammunition shipping could not be checked. Please try again.'));
                }).always(function () { self.autofflChecking = false; });
            },

            destroy: function () {
                clearTimeout(this.autofflTimer);
                if (this.autofflSubscription) {
                    this.autofflSubscription.dispose();
                }
                return this._super();
            }
        });
    };
});
