define([], function () {
    'use strict';

    return {
        isMultishipping: function () {
            var url = window.location.href.toLowerCase();
            return url.includes('multishipping/checkout');
        }
    };
});