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

test('all storefront metrics wait for the current customer identity callback', async () => {
    const identityStart = blade.indexOf('window.paKlaviyoIdentify = function');
    const identityEnd = blade.indexOf('</script>', identityStart);
    const identitySource = blade.slice(identityStart, identityEnd)
        .replace('@json($klaviyoBrowserIdentity)', JSON.stringify({ email: 'test@example.com', $id: '21' }));
    const identityCallbacks = [];
    const calls = [];
    const window = { klaviyo: {
        identify: (identity, callback) => {
            assert.equal(identity.$id, '21');
            identityCallbacks.push(callback);
        },
        track: (metric) => calls.push(metric),
        trackViewedItem: () => calls.push('recently-viewed')
    } };
    vm.runInNewContext(identitySource + source, { window });
    for (const event of ['ViewContent', 'AddToCart', 'InitiateCheckout']) {
        window.paKlaviyoTrack(event, {}, null, {});
    }
    assert.deepEqual(calls, [], 'No event should use the old cookie before identification completes');
    identityCallbacks.shift()();
    await window.paKlaviyoIdentityReady;
    await Promise.resolve();
    assert.equal(calls.length, 0);
    assert.equal(identityCallbacks.length, 3);
    identityCallbacks.splice(0).forEach(callback => callback());
    await new Promise(resolve => setImmediate(resolve));
    assert.deepEqual(calls, ['Viewed Product', 'recently-viewed', 'Added to Cart', 'Started Checkout']);
});

test('failed identification never falls back to tracking the previous customer', async () => {
    const calls = [];
    const warnings = [];
    const window = {
        paKlaviyoIdentityReady: Promise.reject(new Error('identify failed')),
        klaviyo: { track: (...args) => calls.push(args) }
    };
    vm.runInNewContext(source, { window, console: { warn: (...args) => warnings.push(args) } });
    window.paKlaviyoTrack('AddToCart', {}, null, {});
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(calls.length, 0);
    assert.equal(warnings.length, 1);
});

test('authenticated browser identity uses the onsite identifier, not a custom property', () => {
    const identity = blade.slice(blade.indexOf('$klaviyoBrowserIdentity = []'), blade.indexOf('<!-- Klaviyo onsite tracking -->'));
    assert.match(identity, /'\$id' => isset\(\$klaviyoUser->id\) \? \(string\) \$klaviyoUser->id : null/);
    assert.doesNotMatch(identity, /'external_id'\s*=>/);
    const guest = identity.slice(identity.indexOf('$klaviyoGuest ='));
    assert.doesNotMatch(guest, /'\$id'\s*=>/, 'Guests must not inherit a registered customer ID');
});

// Model an SDK identity refresh between separate customer actions.
test('later events reidentify after browser identity drifts', async () => {
    const start = blade.indexOf('window.paKlaviyoIdentify = function');
    const identitySource = blade.slice(start, blade.indexOf('</script>', start))
        .replace('@json($klaviyoBrowserIdentity)', JSON.stringify({ email: 'test@example.com', $id: '21' }));
    let currentIdentity = 'user_21';
    const calls = [];
    const window = { klaviyo: {
        identify: (identity, callback) => { currentIdentity = identity.$id; callback(); },
        track: metric => calls.push([metric, currentIdentity]),
        trackViewedItem: () => { currentIdentity = 'user_21'; }
    } };
    vm.runInNewContext(identitySource + source, { window });
    for (const event of ['ViewContent', 'AddToCart', 'InitiateCheckout']) {
        window.paKlaviyoTrack(event, {}, null, {});
        await new Promise(resolve => setImmediate(resolve));
        currentIdentity = 'user_21';
    }
    assert.deepEqual(calls, [
        ['Viewed Product', '21'], ['Added to Cart', '21'], ['Started Checkout', '21']
    ]);
});

test('failed event-time reidentification suppresses the event', async () => {
    const calls = [];
    const warnings = [];
    const window = {
        paKlaviyoIdentityReady: Promise.resolve(),
        paKlaviyoIdentify: () => Promise.reject(new Error('refresh failed')),
        klaviyo: { track: (...args) => calls.push(args) }
    };
    vm.runInNewContext(source, { window, console: { warn: (...args) => warnings.push(args) } });
    window.paKlaviyoTrack('AddToCart', {}, null, {});
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(calls.length, 0);
    assert.equal(warnings.length, 1);
});

test('identified storefront sends through explicit API once even while SDK identity is pending', async () => {
    const { paCreateKlaviyoEventSender: create } = require('../../../assets/front/js/klaviyo-events.js');
    for (const sdkAvailable of [true, false]) {
        const requests = [], sdkCalls = [], viewed = [];
        const window = {
            paKlaviyoIdentityReady: new Promise(() => {}),
            paKlaviyoSendIdentifiedEvent: create({ publicKey: 'public', identity: { $id: '21', email: 'test@example.com' } }, async (_, options) => {
                requests.push(JSON.parse(options.body)); return { status: 202 };
            })
        };
        if (sdkAvailable) window.klaviyo = { track: (...args) => sdkCalls.push(args), trackViewedItem: item => viewed.push(item) };
        vm.runInNewContext(source, { window });
        for (const event of ['ViewContent', 'AddToCart', 'InitiateCheckout']) {
            window.paKlaviyoTrack(event, { value: 28.91, currency: 'CAD' }, null, { eventId: event, klaviyo: { CartRecoveryAvailable: true, Items: [{ ProductID: '107955' }] } });
        }
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(requests.length, 3);
        assert.equal(sdkCalls.length, 0);
        assert.equal(viewed.length, sdkAvailable ? 1 : 0);
        for (const request of requests) {
            assert.equal(request.data.attributes.profile.data.attributes.external_id, '21');
            if (request.data.attributes.metric.data.attributes.name !== 'Viewed Product') {
                assert.equal(request.data.attributes.properties.CartRecoveryAvailable, true);
                assert.equal(request.data.attributes.properties.Items[0].ProductID, '107955');
            }
        }
    }
});

test('explicit API failure is contained and never falls back to SDK identity', async () => {
    const warnings = [], calls = [];
    const window = {
        paKlaviyoSendIdentifiedEvent: () => Promise.reject(new Error('unavailable')),
        klaviyo: { track: (...args) => calls.push(args) }
    };
    vm.runInNewContext(source, { window, console: { warn: message => warnings.push(message) } });
    assert.doesNotThrow(() => window.paKlaviyoTrack('AddToCart', {}, null, {}));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(calls.length, 0);
    assert.equal(warnings.length, 1);
});
