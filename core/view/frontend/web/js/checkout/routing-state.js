define(['jquery', 'uiComponent', 'ko'], function ($, Component, ko) {
    'use strict';

    return Component.extend({
        defaults: { template: 'RefactoredGroup_AutoFflCore/checkout/routing-state' },
        state: ko.observable(''),
        error: ko.observable(''),
        saving: ko.observable(false),

        initialize: function () {
            this._super();
            var regions = JSON.parse(this.regionJson || '{}').US || {};
            this.states = Object.keys(regions).map(function (id) {
                return { code: regions[id].code, name: regions[id].name };
            }).filter(function (region) {
                return /^[A-Z]{2}$/.test(region.code);
            }).sort(function (a, b) {
                return a.name.localeCompare(b.name);
            });
            this.state(this.selectedState || '');
            return this;
        },

        saveState: function () {
            var self = this;
            if (!this.state() || this.saving()) {
                return;
            }
            this.saving(true);
            this.error('');
            $.post(this.routingStateUrl, { form_key: this.formKey, state: this.state() })
                .done(function () { window.location.reload(); })
                .fail(function () {
                    self.error('The delivery state could not be saved. Please try again.');
                    self.saving(false);
                });
        }
    });
});
