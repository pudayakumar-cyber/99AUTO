<?php

namespace Tests\Unit;

use App\Models\MarketingConsentEvent;
use App\Services\MarketingConsentService;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class MarketingConsentEvidenceTest extends TestCase
{
    private array $jobs = [];
    protected function setUp(): void
    {
        $db = new Manager;
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->setAsGlobal();
        $db->bootEloquent();
        $app = $db->getContainer();
        Container::setInstance($app);
        $app->instance('db', $db->getDatabaseManager());
        $app->bind('db.schema', fn () => $db->schema());
        $bus = \Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
        $bus->shouldReceive('dispatch')->andReturnUsing(function ($job) { $this->jobs[] = $job; });
        $app->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $bus);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        (require __DIR__.'/../../database/migrations/2026_08_30_000000_create_marketing_consents_tables.php')->up();
    }
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        \Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        Model::unsetConnectionResolver();
        parent::tearDown();
    }
    public function test_repeated_updates_preserve_evidence_but_real_transitions_are_recorded(): void
    {
        $service = new MarketingConsentService;
        foreach (['email' => 'test@example.test', 'sms' => '+14165551234'] as $channel => $identity) {
            Carbon::setTestNow('2026-10-24 12:00:00');
            $first = $service->setConsent($channel, $identity, true, 'signup', null, 'Original wording', '127.0.0.1');
            Carbon::setTestNow('2026-10-25 12:00:00');
            $repeat = $service->setConsent($channel, $identity, true, 'profile', null, 'Different wording');
            $this->assertSame($first->consented_at->toAtomString(), $repeat->consented_at->toAtomString());
            $this->assertSame('Original wording', $repeat->consent_text);
            $this->assertSame('signup', $repeat->source);
            $this->assertSame('127.0.0.1', $repeat->last_ip);
            $withdrawn = $service->setConsent($channel, $identity, false, 'profile');
            Carbon::setTestNow('2026-10-26 12:00:00');
            $again = $service->setConsent($channel, $identity, false, 'retry');
            $this->assertSame($withdrawn->revoked_at->toAtomString(), $again->revoked_at->toAtomString());
            $renewed = $service->setConsent($channel, $identity, true, 'new-opt-in', null, 'New wording');
            $this->assertSame('2026-10-26', $renewed->consented_at->toDateString());
            $this->assertNull($renewed->revoked_at);
            $this->assertSame(['subscribed', 'unsubscribed', 'subscribed'],
                MarketingConsentEvent::where('channel', $channel)->orderBy('id')->pluck('action')->all());
        }
        $this->assertCount(6, $this->jobs);
    }
}
