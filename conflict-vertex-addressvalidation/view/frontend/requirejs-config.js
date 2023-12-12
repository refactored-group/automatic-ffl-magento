var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/checkout-data': {
                'Vertex_AddressValidation/js/shipping-invalidate-mixin': false,
                'RefactoredGroup_Vertex_AddressValidation/js/shipping-invalidate-mixin': true
            }
        }
    }
};
