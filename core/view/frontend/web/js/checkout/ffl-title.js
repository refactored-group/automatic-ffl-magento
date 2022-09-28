/**
 * Copyright © Refactored Group (https://www.refactored.group)
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
            template: 'RefactoredGroup_AutoFflCore/checkout/ffl-dealder'
        },
        title: 'FFL Dealer Shipping Address (required for firearms)',
        /** @inheritdoc */
        initialize: function () {
            this._super();
            return this;
        }
    });
});
