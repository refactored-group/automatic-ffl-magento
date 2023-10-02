var config = {
    map: {
        '*': {
            'Magento_Checkout/template/shipping-address/list':
                'RefactoredGroup_AutoFflCheckout/template/shipping-address/list',
            'Magento_Checkout/template/shipping':
                'RefactoredGroup_AutoFflCheckout/template/checkout/shipping'
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/model/checkout-data-resolver': {
                'RefactoredGroup_AutoFflCheckout/js/checkout/model/checkout-data-resolver-mixin': true
            },
            'Magento_Checkout/js/model/new-customer-address': {
                'RefactoredGroup_AutoFflCheckout/js/checkout/model/new-customer-address-mixin': true
            },
            'Magento_Checkout/js/proceed-to-checkout': {
                'RefactoredGroup_AutoFflCheckout/js/proceed-to-checkout-mixin': true
            },
            'Magento_Checkout/js/view/minicart': {
                'RefactoredGroup_AutoFflCheckout/js/view/minicart-mixin': true
            }
        }
    }
};
