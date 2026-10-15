const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

// Execute the actual storefront adapter, replacing only Blade's route literal.
const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/master/front.blade.php'), 'utf8');
const start = blade.indexOf('window.paKlaviyoTrack = function');
const end = blade.indexOf('\n    @endif', start);
assert.ok(start >= 0 && end > start, 'Storefront adapter must exist');
const source = blade.slice(start, end).replace(/@json\(route\('front.checkout.billing'\)\)/g, '"https://example.test/checkout"');

function track(metaEvent, payload = {}, options = {}) {
    const calls = [];
    const window = { klaviyo: { track: (...args) => calls.push(args) } };
    vm.runInNewContext(source, { window });
    window.paKlaviyoTrack(metaEvent, payload, null, options);
    return calls;
}

test('direct checkout omits absent, empty and null event IDs', () => {
    for (const eventId of [undefined, null, '', '   ']) {
        const [[metric, properties]] = track('InitiateCheckout', { value: 28.91 }, { eventId });
        assert.equal(metric, 'Started Checkout');
        assert.equal(Object.hasOwn(properties, '$event_id'), false);
        assert.equal(properties.$value, 28.91);
    }
});

test('existing cart event IDs are preserved for deduplication', () => {
    for (const event of ['InitiateCheckout', 'AddToCart', 'ViewContent']) {
        const [[, properties]] = track(event, { event_id: 'payload-id' }, { eventId: 'explicit-id' });
        assert.equal(properties.$event_id, 'explicit-id');
    }
    assert.equal(track('InitiateCheckout', { event_id: 'payload-id' })[0][1].$event_id, 'payload-id');
    assert.equal(track('InitiateCheckout', {}, { eventId: 123 })[0][1].$event_id, 123);
});

test('checkout recovery details and item data are unchanged', () => {
    const recovery = { CartRecoveryAvailable: true, CheckoutURL: 'https://example.test/cart/recover/test', Items: [{ ProductID: '107955', Quantity: 1 }] };
    const [[, properties]] = track('InitiateCheckout', { currency: 'CAD' }, { klaviyo: recovery });
    assert.equal(properties.CheckoutURL, recovery.CheckoutURL);
    assert.equal(properties.Items, recovery.Items);
    assert.equal(properties.CartRecoveryAvailable, true);
    assert.equal(properties.Currency, 'CAD');
    assert.equal(Object.hasOwn(properties, '$event_id'), false);
});

test('unrelated events are not sent to Klaviyo', () => {
    assert.equal(track('AddShippingInfo').length, 0);
});
