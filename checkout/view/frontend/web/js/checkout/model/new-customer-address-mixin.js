define([
    'mage/utils/wrapper',
    'RefactoredGroup_AutoFflCore/js/checkout/helper/ffl-address'
], function (
    wrapper,
    fflAddress
) {
    'use strict';

    return function (newCustomerAddressModel) {
        return wrapper.wrap(newCustomerAddressModel, function (originalAddressModel, addressData) {
            var result =  originalAddressModel(addressData);
            var canUseForBilling = result.canUseForBilling;

            result.canUseForBilling = function () {
                if (checkoutConfig.customerData.is_ffl == 1 && fflAddress.isDealerAddress(addressData)) {
                    return false;
                }

                if (typeof canUseForBilling === 'function') {
                    return canUseForBilling.apply(result, arguments);
                }

                return true;
            };

            return result;
        });
    };
});
