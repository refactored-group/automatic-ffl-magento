define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/checkout-data',
    'RefactoredGroup_AutoFflCore/js/checkout/helper/ffl-address',
    'ko'
], function ($, customerData, checkoutData, fflAddress, ko) {
    'use strict';

    return function (Component) {
        return Component.extend({
            createRendererComponent: function (address) {
                if (checkoutConfig.customerData.is_ffl == 1 && !this.isFflShippingAddress(address)) {
                    return;
                }

                // Forward every argument (notably `index`) to the parent. The core
                // list component uses `index` as the renderer's unique name; dropping
                // it makes all addresses render under `name: undefined`, so they
                // collide in the registry and collapse to a single card.
                return this._super.apply(this, arguments);
            },

            initialize: function () {
                this._super();

                const checkoutData = customerData.get('checkout-data')();
                this.dealerLicense = ko.observable('');
                if (checkoutConfig.customerData.is_ffl != 1) {
                    this.setDealerLicense(checkoutData);
                }

                customerData.get('checkout-data').subscribe(function (updatedCheckoutData) {
                    this.setDealerLicense(updatedCheckoutData);
                }, this);

                return this;
            },

            isFflShippingAddress: function (address) {
                var newCustomerShippingAddress = checkoutData.getNewCustomerShippingAddress &&
                    checkoutData.getNewCustomerShippingAddress();

                if (fflAddress.isDealerAddress(address)) {
                    return true;
                }

                return Boolean(
                    newCustomerShippingAddress &&
                    fflAddress.isDealerAddress(newCustomerShippingAddress) &&
                    address &&
                    typeof address.getKey === 'function' &&
                    address.getKey() === 'new-customer-address'
                );
            },

            setDealerLicense: function (checkoutData) {
                this.dealerLicense(
                    checkoutData?.newCustomerShippingAddress?.dealer_license ||
                    checkoutData?.newCustomerShippingAddress?.default?.dealer_license ||
                    ''
                );
            },
        });
    };
});
