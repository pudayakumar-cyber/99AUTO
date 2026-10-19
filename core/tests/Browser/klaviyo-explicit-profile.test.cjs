const { test } = require('node:test');
const assert = require('node:assert/strict');
const { paCreateKlaviyoEventSender: create } = require('../../../assets/front/js/klaviyo-events.js');

test('all metrics carry the same explicit profile without inherited SDK identifiers', async () => {
    const requests = [];
    const identity = { $id: '21', email: 'test@example.com', phone_number: '+03328324800',
        first_name: 'Test', $exchange_id: 'old-cookie', _kx: 'old-cookie', external_id: 'user_21' };
    const send = create({ publicKey: 'public-test', identity }, async (url, options) => {
        requests.push({ url, options, data: JSON.parse(options.body).data });
        return { status: 202 };
    });
    // Even if the original object / SDK identity changes, the page's sender is stable.
    identity.$id = 'user_21';
    for (const metric of ['Viewed Product', 'Added to Cart', 'Started Checkout']) {
        await send(metric, { $value: 28.91, $event_id: 'test-' + metric, Currency: 'CAD',
            CartRecoveryAvailable: true, CheckoutURL: 'https://example.test/cart/recover/token',
            Items: [{ ProductID: '107955', Quantity: 1 }] });
    }
    for (const { url, options, data } of requests) {
        assert.equal(url, 'https://a.klaviyo.com/client/events?company_id=public-test');
        assert.equal(options.credentials, 'omit');
        assert.equal(options.headers.Authorization, undefined);
        assert.deepEqual(data.attributes.profile.data.attributes,
            { external_id: '21', email: 'test@example.com', first_name: 'Test' });
        assert.equal(data.attributes.value, 28.91);
        assert.equal(data.attributes.unique_id, 'test-' + data.attributes.metric.data.attributes.name);
        assert.equal(data.attributes.properties.$event_id, undefined);
        assert.equal(data.attributes.properties.$value, undefined);
        assert.equal(data.attributes.properties.Items[0].Quantity, 1);
        assert.equal(data.attributes.properties.CartRecoveryAvailable, true);
    }
});

test('known guests use email without inheriting a registered external ID', async () => {
    let payload;
    await create({ publicKey: 'public', identity: { email: 'guest@example.com' } }, async (_, options) => {
        payload = JSON.parse(options.body); return { status: 202 };
    })('Started Checkout', {});
    assert.deepEqual(payload.data.attributes.profile.data.attributes, { email: 'guest@example.com' });
    assert.equal(payload.data.attributes.unique_id, undefined);
    assert.equal(create({ identity: {} }, () => {}), null);
    assert.equal(create({ identity: { phone_number: '+03328324800' } }, () => {}), null);
});

test('HTTP errors and network failures reject without a second send', async () => {
    for (const status of [400, 429, 500]) {
        let count = 0;
        const send = create({ identity: { $id: '21' } }, async () => { count++; return { status }; });
        await assert.rejects(send('Added to Cart', {}));
        assert.equal(count, 1);
    }
    await assert.rejects(create({ identity: { $id: '21' } }, () => { throw Error('offline'); })('Added to Cart', {}));
});
