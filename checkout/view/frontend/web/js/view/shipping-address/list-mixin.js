define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'ko'
], function ($, customerData, ko) {
    'use strict';

    return function (Component) {
        return Component.extend({
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
