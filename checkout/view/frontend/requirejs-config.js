var config = {
    map: {
        '*': {
            'Magento_Checkout/template/shipping-address/list':
                'Razoyo_AutoFflCheckout/template/shipping-address/list',
            'Magento_Checkout/template/shipping':
                'Razoyo_AutoFflCheckout/template/checkout/shipping'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/model/checkout-data-resolver': {
                'Razoyo_AutoFflCheckout/js/checkout/model/checkout-data-resolver-mixin': true
            },
            'Magento_Checkout/js/model/new-customer-address': {
                'Razoyo_AutoFflCheckout/js/checkout/model/new-customer-address-mixin': true
            }
        }
    }
};
