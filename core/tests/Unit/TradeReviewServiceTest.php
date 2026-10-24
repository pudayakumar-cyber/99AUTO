<?php

namespace Tests\Unit;

use App\Models\CustomerLifecycleProfile;
use App\Services\CustomerLifecycleService;
use App\Services\TradeReviewService;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class TradeReviewServiceTest extends TestCase
{
    private Manager $db;
    private TradeReviewService $service;
    private CustomerLifecycleProfile $profile;
    protected function setUp(): void
    {
        $this->db = new Manager;
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->setAsGlobal(); $this->db->bootEloquent();
        $app = $this->db->getContainer(); Container::setInstance($app);
        $app->instance('db', $this->db->getDatabaseManager());
        $app->bind('db.schema', fn () => $this->db->schema());
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($app);
        (require __DIR__.'/../../database/migrations/2026_09_05_000000_create_customer_lifecycle_tables.php')->up();
        (require __DIR__.'/../../database/migrations/2026_10_24_000000_create_trade_review_decisions.php')->up();
        $sync = \Mockery::mock(CustomerLifecycleService::class);
        $sync->shouldReceive('sync')->with(\Mockery::type(CustomerLifecycleProfile::class))->andReturnNull();
        $this->service = new TradeReviewService($sync);
        $this->profile = CustomerLifecycleProfile::create(['user_id' => 21, 'identity_hash' => hash('sha256', 'test'),
            'trade_review_status' => 'pending', 'buyer_type' => 'DIY']);
    }
    protected function tearDown(): void
    {
        \Mockery::close(); Facade::clearResolvedInstances(); Facade::setFacadeApplication(null);
        Container::setInstance(null); Model::unsetConnectionResolver(); parent::tearDown();
    }
    public function test_review_is_audited_and_retry_cannot_undo_a_later_rejection(): void
    {
        $approved = $this->service->decide($this->profile->id, 'approved', 'pending', 'operator', 'case-1');
        $this->assertSame('Trade', $approved->buyer_type);
        $this->service->decide($this->profile->id, 'approved', 'pending', 'operator', 'case-1');
        $this->assertSame(1, $this->db->table('trade_review_decisions')->count());
        $this->service->decide($this->profile->id, 'rejected', 'approved', 'operator', 'case-2');
        $retry = $this->service->decide($this->profile->id, 'approved', 'pending', 'operator', 'case-1');
        $this->assertSame('rejected', $retry->trade_review_status);
        $this->assertSame('DIY', $retry->buyer_type);
        $this->assertSame(2, $this->db->table('trade_review_decisions')->count());
    }
    public function test_stale_review_and_guest_approval_are_rejected_without_audit_writes(): void
    {
        try {
            $this->service->decide($this->profile->id, 'approved', 'not_requested', 'operator', 'stale');
            $this->fail('Stale review accepted');
        } catch (\RuntimeException) {
            $this->assertSame(0, $this->db->table('trade_review_decisions')->count());
        }
        $this->profile->update(['user_id' => null]);
        try {
            $this->service->decide($this->profile->id, 'approved', 'pending', 'operator', 'guest');
            $this->fail('Guest approved');
        } catch (\RuntimeException) {
            $this->assertSame(0, $this->db->table('trade_review_decisions')->count());
        }
    }
    public function test_command_previews_without_writing_and_apply_requires_review_details(): void
    {
        $command = new \App\Console\Commands\ReviewTradeProfile;
        $app = new class extends Container { public function runningUnitTests(): bool { return true; } };
        $app->instance(TradeReviewService::class, $this->service);
        $command->setLaravel($app);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $args = ['profile' => $this->profile->id, 'decision' => 'approved'];
        $this->assertSame(0, $tester->execute($args));
        $this->assertSame('pending', $this->profile->fresh()->trade_review_status);
        $this->assertSame(1, $tester->execute($args + ['--apply' => true]));
        $this->assertSame(0, $this->db->table('trade_review_decisions')->count());
        $this->assertSame(0, $tester->execute($args + ['--apply' => true, '--reviewer' => 'operator', '--reference' => 'verified-case']));
        $this->assertSame('approved', $this->profile->fresh()->trade_review_status);
    }
}
