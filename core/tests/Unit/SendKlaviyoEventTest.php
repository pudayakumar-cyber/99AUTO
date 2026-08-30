<?php

namespace Tests\Unit;

use App\Jobs\SendKlaviyoEvent;
use App\Services\KlaviyoClient;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Mockery;
use PHPUnit\Framework\TestCase;

class SendKlaviyoEventTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container;
        Container::setInstance($app);
        $app->instance('config', new Repository([
            'services' => [
                'klaviyo' => ['queue' => 'default'],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_it_does_not_call_the_api_when_klaviyo_is_disabled(): void
    {
        $client = Mockery::mock(KlaviyoClient::class);
        $client->shouldReceive('enabled')->once()->andReturnFalse();
        $client->shouldNotReceive('trackEvent');

        (new SendKlaviyoEvent('Viewed Product', ['email' => 'customer@example.com']))->handle($client);
        $this->addToAssertionCount(1);
    }

    public function test_it_forwards_event_data_when_klaviyo_is_enabled(): void
    {
        $client = Mockery::mock(KlaviyoClient::class);
        $client->shouldReceive('enabled')->once()->andReturnTrue();
        $client->shouldReceive('trackEvent')
            ->once()
            ->with(
                'Added to Cart',
                ['email' => 'customer@example.com'],
                ['ProductID' => 99],
                'cart-99',
                Mockery::type(\DateTimeImmutable::class),
                19.95
            );

        (new SendKlaviyoEvent(
            'Added to Cart',
            ['email' => 'customer@example.com'],
            ['ProductID' => 99],
            'cart-99',
            '2026-08-30T12:00:00+00:00',
            19.95
        ))->handle($client);
        $this->addToAssertionCount(1);
    }
}
