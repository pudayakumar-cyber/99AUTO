const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/master/front.blade.php'), 'utf8');
const start = blade.indexOf('(function (window) {', blade.indexOf('<!-- Klaviyo onsite tracking -->'));
const end = blade.indexOf('</script>', start);
assert.ok(start >= 0 && end > start);
const source = blade.slice(start, end);

for (const fallback of [false, true]) {
    test(`startup retains checkout data and resolves SDK callbacks (fallback=${fallback})`, async () => {
        const window = {};
        vm.runInNewContext(source, { window, Proxy: fallback ? undefined : Proxy, Promise });
        const identity = { email: 'test@example.invalid' };
        const properties = { Currency: 'CAD', Items: [{ ProductID: '107955' }] };
        let callbackResult;
        const identified = window.klaviyo.identify(identity);
        const tracked = window.klaviyo.track('Started Checkout', properties, result => { callbackResult = result; });
        assert.equal(window._klOnsite.length, 2);
        // Klaviyo's documented queued method contract ends with a callback.
        const identify = window._klOnsite.shift();
        const identifyCallback = identify.pop();
        assert.equal(identify[0], 'identify');
        assert.equal(identify[1], identity);
        identifyCallback(identity);
        const event = window._klOnsite.shift();
        const eventCallback = event.pop();
        assert.equal(event[0], 'track');
        assert.equal(event[1], 'Started Checkout');
        assert.equal(event[2], properties);
        eventCallback(true);
        assert.equal(await identified, identity);
        assert.equal(await tracked, true);
        assert.equal(callbackResult, true);
        const pushed = ['track', 'Custom Event', {}];
        window.klaviyo.push(pushed);
        assert.equal(window._klOnsite[0], pushed);
    });
}

test('existing loaded SDK is not replaced', () => {
    const sdk = { track() {} };
    const window = { klaviyo: sdk };
    vm.runInNewContext(source, { window });
    assert.equal(window.klaviyo, sdk);
    assert.equal(window._klOnsite, undefined);
});
