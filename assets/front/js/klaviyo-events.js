(function (root) {
    'use strict';

    // Client Events uses only the public site ID. Never add an authorization key.
    root.paCreateKlaviyoEventSender = function (config, fetchRequest) {
        var identity = config.identity || {};
        var profile = {};
        if (identity.$id) profile.external_id = String(identity.$id);
        if (identity.email) profile.email = identity.email;
        // A malformed optional phone must not prevent an email customer's event.
        if (!profile.email && /^\+[1-9][0-9]{7,14}$/.test(identity.phone_number || '')) {
            profile.phone_number = identity.phone_number;
        }
        if (!profile.email && !profile.phone_number && !profile.external_id) return null;
        if (identity.first_name) profile.first_name = identity.first_name;
        if (identity.last_name) profile.last_name = identity.last_name;

        return function (metric, properties) {
            var eventProperties = Object.assign({}, properties);
            var attributes = {
                metric: { data: { type: 'metric', attributes: { name: metric } } },
                profile: { data: { type: 'profile', attributes: Object.assign({}, profile) } },
                properties: eventProperties
            };
            if (typeof properties.$value === 'number' && isFinite(properties.$value)) {
                attributes.value = properties.$value;
            }
            if (properties.$event_id !== undefined && properties.$event_id !== null &&
                String(properties.$event_id).trim() !== '') {
                attributes.unique_id = String(properties.$event_id);
            }
            delete eventProperties.$value;
            delete eventProperties.$event_id;

            // Do not use SDK cookies, exchange IDs, or a fallback SDK send here:
            // a fallback could attach the same action to another customer profile.
            return Promise.resolve().then(function () {
                return fetchRequest('https://a.klaviyo.com/client/events?company_id=' + encodeURIComponent(config.publicKey), {
                    method: 'POST',
                    credentials: 'omit',
                    headers: {
                        'Content-Type': 'application/vnd.api+json',
                        'Accept': 'application/vnd.api+json',
                        'revision': '2026-07-15'
                    },
                    body: JSON.stringify({ data: { type: 'event', attributes: attributes } })
                });
            }).then(function (response) {
                if (response.status !== 202) throw new Error('Klaviyo event was not accepted.');
            });
        };
    };
})(typeof window === 'undefined' ? module.exports : window);
