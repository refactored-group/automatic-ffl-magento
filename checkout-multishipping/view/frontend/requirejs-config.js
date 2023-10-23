var config = {
    map: {
        '*': {
            proceedToCheckoutMultipleAddresses: 'RefactoredGroup_AutoFflCheckoutMultiShipping/js/checkout/proceed-to-checkout-multiple-addresses',
            styleShippingAddressTable: 'RefactoredGroup_AutoFflCheckoutMultiShipping/js/checkout/style-shipping-address-table'
        }
    },
    config: {
        mixins: {
            'Magento_Multishipping/js/multi-shipping': {
                'RefactoredGroup_AutoFflCheckoutMultiShipping/js/multi-shipping-mixin': true
            }
        }
    }
};
