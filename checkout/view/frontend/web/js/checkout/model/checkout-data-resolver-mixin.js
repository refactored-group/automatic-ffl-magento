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
        /**
         * Get shipping address from address list. This method is required for Magento 2.3.x
         *
         * @return {Object|null}
         */
        getShippingAddressFromCustomerAddressList: function () {
            var shippingAddress = _.find(
                addressList(),
                function (address) {
                    return checkoutData.getSelectedShippingAddress() == address.getKey() //eslint-disable-line
                }
            );

            if (!shippingAddress) {
                shippingAddress = _.find(
                    addressList(),
                    function (address) {
                        return address.isDefaultShipping();
                    }
                );
            }

            if (!shippingAddress && addressList().length === 1) {
                shippingAddress = addressList()[0];
            }

            return shippingAddress;
        },

        applyShippingAddress: function (isEstimatedAddress) {
            var address,
                shippingAddress,
                isConvertAddress;

            var data = storage.get('checkout-data')();

            if (addressList().length === 0) {
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
