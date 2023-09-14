/*jshint browser:true jquery:true*/
/*global alert*/
define([
    'underscore',
], function (_) {
    'use strict';

    /*
     * This file is to override 
     * magento/module-checkout/view/frontend/web/js/view/shipping-information/address-renderer/default.js
     * for the sake of preventing the user from seeing the ffl_license in the shipping address in checkout
     */
    return function (targetModule) {
        return targetModule.extend({
            getCustomAttributeLabel:function(attribute)
            {
                if(!_.isUndefined(attribute)
                    && !_.isUndefined(attribute.value)
                    && !_.isUndefined(attribute.attribute_code)
                ) {
                    if(attribute.attribute_code === 'ffl_license') {
                        return '';
                    }
                }

                return this._super(attribute);
            }
        });
    };
});
