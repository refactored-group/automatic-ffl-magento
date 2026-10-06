const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

const handlers = {};
const requests = [];
let formData = [{name: 'form_key', value: 'fixture'}, {name: 'continue', value: 1},
    {name: 'ship[1][2][qty]', value: 2}, {name: 'ffl_destination[1][2]', value: 'CA'}];
let submissions = 0;
const message = {hidden: true, value: ''};
const error = {
    prop(_, value) { message.hidden = value; return this; },
    text(value) { message.value = value; return this; }
};
const form = {action: '/multishipping/checkout/addressesPost'};
const wrapper = {
    find() { return error; },
    serializeArray() { return formData; },
    attr() { return this; },
    on(events, listener) { events.split(' ').forEach(event => { handlers[event] = listener; }); return this; },
    trigger(event,args=[]) {
        let prevented = false;
        handlers[event]({preventDefault() { prevented = true; }},...args);
        if (event === 'submit' && !prevented) submissions++;
        return this;
    }
};
const $ = () => wrapper;
$.mage = {__: value => value};
$.ajax = options => {
    const request = {options}; requests.push(request);
    return {done(callback) { request.done = callback; return this; },
        fail(callback) { request.fail = callback; return this; }};
};
let initialize;
vm.runInNewContext(fs.readFileSync('core/view/frontend/web/js/cart/multishipping-destinations.js', 'utf8'), {
    define: (_, factory) => { initialize = factory($); }
});
initialize({}, form);
wrapper.trigger('automaticffl:destination-changed');
assert.strictEqual(requests.length, 1);
assert(requests[0].options.data.some(field => field.name === 'form_key'), 'Background save must retain native CSRF token.');
assert(requests[0].options.data.some(field => field.name === 'continue' && field.value === 0));
formData = formData.map(field => field.name === 'ffl_destination[1][2]' ? {...field, value: 'TX'} : field);
wrapper.trigger('automaticffl:destination-changed');
formData = formData.map(field => field.name === 'ffl_destination[1][2]' ? {...field, value: 'NY'} : field);
wrapper.trigger('automaticffl:destination-changed');
wrapper.trigger('submit');
assert.strictEqual(requests.length, 1, 'Rapid changes must serialize requests.');
assert.strictEqual(submissions, 0, 'Continue must wait until the latest destination is saved.');
requests[0].done({success: true});
assert.strictEqual(requests.length, 2);
assert.strictEqual(requests[1].options.data.find(field => field.name === 'ffl_destination[1][2]').value, 'NY',
    'Intermediate destinations must coalesce to the final selection.');
requests[1].done({success: true});
assert.strictEqual(submissions, 1, 'A queued Continue should resume after successful persistence.');
wrapper.trigger('automaticffl:dealer-selected');
assert.strictEqual(requests.length, 3, 'Selecting a dealer must persist every active dealer group member.');
wrapper.trigger('submit');
requests[2].fail({responseJSON: {message: 'Destination unavailable.'}});
assert.strictEqual(submissions, 1, 'Failed saves must block continuation.');
assert.strictEqual(message.hidden, false);
assert.strictEqual(message.value, 'Destination unavailable.');
wrapper.trigger('submit');
assert.strictEqual(requests.length, 4, 'A customer can retry a failed save by continuing again.');
requests[3].done({success: true});
assert.strictEqual(submissions, 2);
assert.strictEqual(message.hidden, true);
wrapper.trigger('automaticffl:dealer-saving');
wrapper.trigger('submit');
wrapper.trigger('automaticffl:destination-changed');
assert.strictEqual(submissions, 2, 'Continue must wait for dealer address creation.');
assert.strictEqual(requests.length, 4, 'Destination persistence must wait for the new dealer ID.');
wrapper.trigger('automaticffl:dealer-selected');
assert.strictEqual(requests.length, 5, 'The final dealer assignment saves the latest destination.');
requests[4].done({success: true});
assert.strictEqual(submissions, 3, 'Continue resumes only after dealer creation and grouping both succeed.');

wrapper.trigger('automaticffl:destination-changed');
wrapper.trigger('automaticffl:dealer-saving');
wrapper.trigger('submit');
requests[5].done({success: true});
assert.strictEqual(submissions, 3, 'A prior destination save cannot release Continue while a dealer is pending.');
wrapper.trigger('automaticffl:dealer-save-failed');
assert.strictEqual(submissions, 3, 'Dealer creation failure must cancel a queued Continue.');
wrapper.trigger('submit');
assert.strictEqual(submissions, 4, 'After a failed replacement the customer can continue with their original dealer.');
let dealerVersion;
const previousRequests=requests.length;
wrapper.trigger('automaticffl:dealer-saving',[version=>{dealerVersion=version;}]);
wrapper.trigger('submit');
wrapper.trigger('automaticffl:dealer-selected',[{persisted:true,version:dealerVersion}]);
assert.strictEqual(requests.length,previousRequests,'An unchanged successful combined save must not start a second address request.');
assert.strictEqual(submissions,5,'Combined assignment success must release queued Continue.');
wrapper.trigger('automaticffl:destination-changed');
let dispatched=false;
wrapper.trigger('automaticffl:dealer-saving',[version=>{dispatched=true;dealerVersion=version;}]);
assert.strictEqual(dispatched,false,'Dealer save must wait for an older native destination save.');
requests[6].done({success:true});
assert(dispatched);
wrapper.trigger('automaticffl:destination-changed');
wrapper.trigger('automaticffl:dealer-selected',[{persisted:true,version:dealerVersion}]);
assert.strictEqual(requests.length,previousRequests+2,'A destination changed during selection needs exactly one follow-up save.');
requests[7].done({success:true});
console.log('Background multishipping saves, coalesced changes, native form key, and queued continuation passed.');
