define(['mage/utils/wrapper',
    'Magento_Customer/js/customer-data',
    'Magento_Customer/js/model/address-list',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/action/create-shipping-address',
    'Magento_Checkout/js/action/select-shipping-address',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/model/payment-service',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Checkout/js/model/address-converter',
    'Magento_Checkout/js/action/select-billing-address',
    'Magento_Checkout/js/action/create-billing-address',
    'underscore'
], function (
    wrapper,
    storage,
    addressList,
    quote,
    checkoutData,
    createShippingAddress,
    selectShippingAddress,
    selectShippingMethodAction,
    paymentService,
    selectPaymentMethodAction,
    addressConverter,
    selectBillingAddress,
    createBillingAddress,
    _
) {
    'use strict';

    var mixin = {

        applyShippingAddress: function (isEstimatedAddress) {
            var address,
                shippingAddress,
                isConvertAddress;

            var data = storage.get('checkout-data')();

            if (addressList().length === 0 && data['selectedShippingAddress']) {
                address = addressConverter.formAddressDataToQuoteAddress(
                    checkoutData.getShippingAddressFromData()
                );
                selectShippingAddress(address);
            }


            shippingAddress = quote.shippingAddress();
            isConvertAddress = isEstimatedAddress || false;

            if (!shippingAddress) {
                shippingAddress = this.getShippingAddressFromCustomerAddressList();

                if (shippingAddress) {
                    selectShippingAddress(
                        isConvertAddress ?
                            addressConverter.addressToEstimationAddress(shippingAddress)
                            : shippingAddress
                    );
                }
            }
        }
    };

    return function (target) {
        return wrapper.extend(target, mixin);
    };
});
