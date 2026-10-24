<?php

namespace Tests\Unit;

use App\Jobs\SyncKlaviyoProfileConsent;
use App\Models\MarketingConsent;
use App\Services\KlaviyoClient;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class KlaviyoUnsubscribeJobTest extends TestCase
{
    protected function setUp(): void
    {
        $db = new Manager;
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->setAsGlobal(); $db->bootEloquent();
        $app = $db->getContainer(); Container::setInstance($app);
        $app->instance('db', $db->getDatabaseManager());
        $app->bind('db.schema', fn () => $db->schema());
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($app);
        (require __DIR__.'/../../database/migrations/2026_08_30_000000_create_marketing_consents_tables.php')->up();
    }
    protected function tearDown(): void
    {
        \Mockery::close(); Facade::clearResolvedInstances(); Facade::setFacadeApplication(null);
        Container::setInstance(null); Model::unsetConnectionResolver(); parent::tearDown();
    }
    private function consent(string $channel, string $status): MarketingConsent
    {
        return MarketingConsent::create(['channel' => $channel, 'identity' => $channel === 'email' ? 'test@example.test' : '+14165551234',
            'identity_hash' => hash('sha256', $channel), 'status' => $status, 'source' => 'profile']);
    }
    public function test_unsubscribe_does_not_require_list_or_profile_upsert(): void
    {
        $client = \Mockery::mock(KlaviyoClient::class);
        $client->shouldReceive('enabled')->twice()->andReturn(true);
        $client->shouldNotReceive('upsertProfile');
        $client->shouldNotReceive('subscribeProfile');
        foreach (['email', 'sms'] as $channel) {
            $consent = $this->consent($channel, 'unsubscribed');
            $client->shouldReceive('unsubscribeProfile')->once()->with($channel,
                [$channel === 'email' ? 'email' : 'phone_number' => $consent->identity], '');
            (new SyncKlaviyoProfileConsent($consent->id))->handle($client);
        }
        $this->assertTrue(true);
    }
    public function test_subscribe_still_requires_a_configured_list(): void
    {
        $client = \Mockery::mock(KlaviyoClient::class);
        $client->shouldReceive('enabled')->once()->andReturn(true);
        $client->shouldNotReceive('subscribeProfile');
        $this->expectException(\RuntimeException::class);
        (new SyncKlaviyoProfileConsent($this->consent('email', 'subscribed')->id))->handle($client);
    }
}
