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
                this.dealerLicense = ko.observable(checkoutData?.newCustomerShippingAddress?.dealer_license || '');

                customerData.get('checkout-data').subscribe(function (updatedCheckoutData) {
                    this.dealerLicense(updatedCheckoutData?.newCustomerShippingAddress?.dealer_license || '');
                }, this);

                return this;
            }
        });
    };
});
