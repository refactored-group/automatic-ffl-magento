define([
    'mage/utils/wrapper',
], function (
    wrapper
) {
    'use strict';

    return function (newCustomerAddressModel) {
        return wrapper.wrap(newCustomerAddressModel, function (originalAddressModel, addressData) {
            var result =  originalAddressModel(addressData);
            result.canUseForBilling = function () {
                if (checkoutConfig.customerData.is_ffl == 1) {
                    return false;
                }
                return true;
            };
            return result;
        });
    };
});
