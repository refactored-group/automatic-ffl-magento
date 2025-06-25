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
            'Magento_Checkout/js/model/new-customer-address': {
                'RefactoredGroup_AutoFflCheckout/js/checkout/model/new-customer-address-mixin': true
            },
            'Magento_Checkout/js/view/shipping-address/list': {
                'RefactoredGroup_AutoFflCheckout/js/view/shipping-address/list-mixin': true
            }
        }
    }
};
