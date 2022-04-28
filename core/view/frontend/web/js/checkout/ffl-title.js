/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'uiComponent',
    'ko',
], function ($, Component, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Razoyo_AutoFflCore/checkout/ffl-dealder'
        },
        title: 'FFL Dealer Shipping Address (required for firearms)',
        /** @inheritdoc */
        initialize: function () {
            this._super();
            return this;
        }
    });
});
