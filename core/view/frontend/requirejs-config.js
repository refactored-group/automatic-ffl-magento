var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/model/shipping-save-processor/payload-extender': {
                'RefactoredGroup_AutoFflCore/js/action/set-shipping-information': true
            },
            'Magento_Checkout/js/view/shipping-information/address-renderer/default': {
                'RefactoredGroup_AutoFflCore/js/action/hide-ffl-license': true
            },
        }
    }
};
