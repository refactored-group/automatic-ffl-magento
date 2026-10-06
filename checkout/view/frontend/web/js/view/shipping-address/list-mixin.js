define([
    'ko',
    'RefactoredGroup_AutoFflCore/js/checkout/destination',
    'RefactoredGroup_AutoFflCore/js/checkout/routing',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer'
], function (ko, destination, routing, quote, customer) {
    'use strict';

    return function (Component) {
        return Component.extend({
            initialize: function () {
                this.requiresDealer = destination.requiresDealer;
                this.isCustomerLoggedIn = customer.isLoggedIn;
                this.dealerLicense = ko.pureComputed(function () {
                    return routing.dealerLicense(quote.shippingAddress());
                }, this);
                this._super();
                this.autofflRecipientSubscription = quote.shippingAddress.subscribe(function (selected) {
                    if (!routing.isDealer(selected)) {
                        return;
                    }
                    this.elems().forEach(function (renderer) {
                        if (renderer.address && this.isAvailableAddress(renderer.address())) {
                            renderer.address(selected);
                        }
                    }, this);
                }, this);

                return this;
            },
            isAvailableAddress: function (address) {
                var selected = quote.shippingAddress();
                var license = routing.dealerLicense(address);
                // Native rate validation creates a new quote Address object.
                // Match the selected dealer by its stable key and license.
                return destination.requiresDealer()
                    ? routing.isDealer(address) && routing.isDealer(selected) &&
                        (selected === address || (license && license === routing.dealerLicense(selected) &&
                            address.getKey() === selected.getKey()))
                    : customer.isLoggedIn() && !routing.isDealer(address);
            },
            destroy: function () {
                this.dealerLicense.dispose();
                this.autofflRecipientSubscription.dispose();
                return this._super();
            }
        });
    };
});
