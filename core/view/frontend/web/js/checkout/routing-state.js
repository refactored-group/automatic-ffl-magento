define(['jquery', 'uiComponent', 'ko', 'RefactoredGroup_AutoFflCore/js/checkout/routing',
    'RefactoredGroup_AutoFflCore/js/checkout/destination'], function ($, Component, ko, routing, destination) {
    'use strict';

    return Component.extend({
        defaults: { template: 'RefactoredGroup_AutoFflCore/checkout/routing-state', beforeCheckout: false },
        state: ko.observable(''),
        error: ko.observable(''),
        saving: ko.observable(false),

        initialize: function () {
            this._super();
            this.state = this.beforeCheckout ? ko.observable('') : destination.state;
            this.error = this.beforeCheckout ? ko.observable('') : destination.error;
            this.saving = this.beforeCheckout ? ko.observable(false) : destination.saving;
            var regions = JSON.parse(this.regionJson || '{}').US || {};
            this.states = Object.keys(regions).map(function (id) {
                return { code: regions[id].code, name: regions[id].name };
            }).filter(function (region) {
                return /^[A-Z]{2}$/.test(region.code);
            }).sort(function (a, b) {
                return a.name.localeCompare(b.name);
            });
            if (this.beforeCheckout) {
                this.state(this.selectedState || '');
            }
            return this;
        },

        saveState: function () {
            var self = this;
            if (!this.beforeCheckout) {
                destination.update(this.state());
                return;
            }
            if (!this.state() || this.saving()) {
                return;
            }
            this.saving(true);
            this.error('');
            routing.check({ stateUrl: this.routingStateUrl, formKey: this.formKey }, null, this.state())
                .done(function (result) {
                    self.saving(false);
                    routing.continueTo(result);
                })
                .fail(function () {
                    self.error('The delivery state could not be saved. Please try again.');
                    self.saving(false);
                });
        }
    });
});
