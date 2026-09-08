<?php

namespace Tests\Unit;

use App\Services\KlaviyoClient;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class KlaviyoClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container;
        Container::setInstance($app);
        $app->instance('config', new Repository([
            'services' => [
                'klaviyo' => [
                    'enabled' => true,
                    'private_api_key' => 'test-private-key',
                    'revision' => '2026-07-15',
                    'base_url' => 'https://a.klaviyo.test',
                    'timeout' => 10,
                    'connect_timeout' => 5,
                    'queue' => 'marketing',
                ],
            ],
        ]));
        $app->instance('http', new Factory);
        Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_it_sends_an_idempotent_event_with_the_expected_headers_and_payload(): void
    {
        Http::fake([
            'a.klaviyo.test/api/events/' => Http::response('', 202),
        ]);

        (new KlaviyoClient)->trackEvent(
            'Placed Order',
            ['email' => 'customer@example.com', 'external_id' => 'customer-10'],
            ['OrderId' => 55, 'Items' => [['ProductID' => 99]]],
            'order-55',
            new \DateTimeImmutable('2026-08-30T12:00:00+00:00'),
            149.95
        );

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://a.klaviyo.test/api/events/'
                && $request->hasHeader('Authorization', 'Klaviyo-API-Key test-private-key')
                && $request->hasHeader('revision', '2026-07-15')
                && $request['data']['type'] === 'event'
                && $request['data']['attributes']['metric']['data']['attributes']['name'] === 'Placed Order'
                && $request['data']['attributes']['profile']['data']['attributes']['email'] === 'customer@example.com'
                && $request['data']['attributes']['unique_id'] === 'order-55'
                && $request['data']['attributes']['value'] === 149.95;
        });
    }

    public function test_it_upserts_a_profile_with_custom_properties(): void
    {
        Http::fake([
            'a.klaviyo.test/api/profile-import/' => Http::response([], 201),
        ]);

        (new KlaviyoClient)->upsertProfile(
            ['email' => 'customer@example.com', 'first_name' => 'Pat'],
            ['buyer_type' => 'DIY']
        );

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://a.klaviyo.test/api/profile-import/'
                && $request['data']['attributes']['email'] === 'customer@example.com'
                && $request['data']['attributes']['properties']['buyer_type'] === 'DIY';
        });
    }

    public function test_it_rejects_events_without_a_profile_identifier(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        (new KlaviyoClient)->trackEvent('Viewed Product', ['first_name' => 'Pat']);
    }

    public function test_it_rejects_an_empty_metric_name(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        (new KlaviyoClient)->trackEvent(' ', ['email' => 'customer@example.com']);
    }

    public function test_it_sends_a_list_scoped_email_subscription(): void
    {
        Http::fake([
            'a.klaviyo.test/api/profile-subscription-bulk-create-jobs' => Http::response('', 202),
        ]);

        (new KlaviyoClient)->subscribeProfile(
            'email',
            ['email' => 'customer@example.com'],
            'email-list-id',
            'footer_newsletter'
        );

        Http::assertSent(function ($request): bool {
            $data = $request['data'];

            return $request->url() === 'https://a.klaviyo.test/api/profile-subscription-bulk-create-jobs'
                && $data['attributes']['custom_source'] === 'footer_newsletter'
                && $data['attributes']['historical_import'] === false
                && $data['attributes']['profiles']['data'][0]['attributes']['email'] === 'customer@example.com'
                && $data['attributes']['profiles']['data'][0]['attributes']['subscriptions']['email']['marketing']['consent'] === 'SUBSCRIBED'
                && $data['relationships']['list']['data']['id'] === 'email-list-id';
        });
    }

    public function test_it_sends_a_list_scoped_sms_unsubscribe(): void
    {
        Http::fake([
            'a.klaviyo.test/api/profile-subscription-bulk-delete-jobs' => Http::response('', 202),
        ]);

        (new KlaviyoClient)->unsubscribeProfile(
            'sms',
            ['phone_number' => '+14165551234'],
            'sms-list-id'
        );

        Http::assertSent(function ($request): bool {
            $data = $request['data'];

            return $request->url() === 'https://a.klaviyo.test/api/profile-subscription-bulk-delete-jobs'
                && $data['attributes']['profiles']['data'][0]['attributes']['phone_number'] === '+14165551234'
                && $data['relationships']['list']['data']['id'] === 'sms-list-id';
        });
    }
}
