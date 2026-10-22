<?php

namespace Tests\Unit;

use App\Models\PromoCode;
use App\Services\MarketingOfferIssuer;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class MarketingOfferIssuerTest extends TestCase
{
    protected Capsule $db;
    protected MarketingOfferIssuer $issuer;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Requires isolated SQLite.');
        }
        $this->db = new Capsule;
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->setAsGlobal();
        $this->db->bootEloquent();
        $app = $this->db->getContainer();
        Container::setInstance($app);
        $app->instance('db', $this->db->getDatabaseManager());
        $app->bind('db.schema', fn () => $this->db->schema());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        $this->db->schema()->create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code_name')->unique();
            $table->integer('status');
            $table->integer('no_of_times');
            $table->string('type');
            $table->decimal('discount');
        });
        (require __DIR__.'/../../database/migrations/2026_10_23_010000_add_marketing_rules_to_promo_codes.php')->up();
        (require __DIR__.'/../../database/migrations/2026_10_23_020000_add_offer_key_to_promo_codes.php')->up();
        $this->db->schema()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
        });
        $this->db->schema()->create('orders', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('transaction_number');
            $table->string('order_status');
            $table->text('billing_info')->nullable();
        });
        $this->db->table('users')->insert([
            ['id' => 21, 'email' => 'CUSTOMER@example.test'],
            ['id' => 22, 'email' => 'other@example.test'],
        ]);
        Carbon::setTestNow('2026-10-23 12:00:00');
        CarbonImmutable::setTestNow('2026-10-23 12:00:00');
        $this->issuer = new MarketingOfferIssuer;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        Model::unsetConnectionResolver();
        parent::tearDown();
    }

    public function test_issuance_binds_recipient_and_retry_does_not_reset_usage_or_expiry(): void
    {
        $expiry = CarbonImmutable::parse('2026-10-30T12:00:00Z');
        $coupon = $this->issuer->issue(21, 'cart', 'cart-123', $expiry);
        $this->assertSame('customer@example.test', $coupon->recipient_email);
        $this->assertSame(1, $coupon->no_of_times);
        $this->assertSame(10, $coupon->discount);
        $this->assertFalse($coupon->first_order_only);
        $coupon->update(['no_of_times' => 0, 'status' => 0]);
        $retry = $this->issuer->issue(21, 'cart', 'cart-123', $expiry->addDays(10));
        $this->assertSame($coupon->id, $retry->id);
        $this->assertSame(0, $retry->no_of_times);
        $this->assertSame(0, $retry->status);
        $this->assertSame('2026-10-30', $retry->expires_at->toDateString());
        $this->assertSame(1, PromoCode::count());
    }

    public function test_customer_and_campaign_references_generate_distinct_codes(): void
    {
        $expiry = CarbonImmutable::now()->addDays(7);
        $a = $this->issuer->issue(21, 'winback', 'campaign-1', $expiry);
        $b = $this->issuer->issue(22, 'winback', 'campaign-1', $expiry);
        $c = $this->issuer->issue(21, 'winback', 'campaign-2', $expiry);
        $this->assertSame(15, $a->discount);
        $this->assertNotSame($a->code_name, $b->code_name);
        $this->assertNotSame($a->code_name, $c->code_name);
    }

    public function test_welcome_offer_requires_no_prior_final_order(): void
    {
        $expiry = CarbonImmutable::now()->addDays(9);
        $coupon = $this->issuer->issue(21, 'welcome', 'signup-1', $expiry);
        $this->assertTrue($coupon->first_order_only);
        $this->db->table('orders')->insert(['user_id' => 22, 'transaction_number' => 'ORD-2', 'order_status' => 'Delivered']);
        $this->expectException(\RuntimeException::class);
        $this->issuer->issue(22, 'welcome', 'signup-2', $expiry);
    }

    public function test_past_expiry_cannot_create_an_offer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->issuer->issue(21, 'cart', 'cart-old', CarbonImmutable::now()->subSecond());
    }

    public function test_expired_retry_does_not_create_a_replacement(): void
    {
        $expiry = CarbonImmutable::now()->addDay();
        $coupon = $this->issuer->issue(21, 'cart', 'cart-1', $expiry);
        CarbonImmutable::setTestNow('2026-11-01');
        $retry = $this->issuer->issue(21, 'cart', 'cart-1', $expiry);
        $this->assertSame($coupon->id, $retry->id);
        $this->assertSame(1, PromoCode::count());
    }

    public function test_command_defaults_to_no_writes_and_rejects_invalid_dates(): void
    {
        $command = new \App\Console\Commands\IssueMarketingOffer;
        $console = new class extends Container {
            public function runningUnitTests(): bool { return true; }
        };
        $console->instance(MarketingOfferIssuer::class, $this->issuer);
        $command->setLaravel($console);
        $tester = new \Symfony\Component\Console\Tester\CommandTester($command);
        $this->assertSame(0, $tester->execute([
            'user' => '21', 'offer' => 'cart', '--reference' => 'cart-1',
            '--expires-at' => '2026-11-01T12:00:00Z',
        ]));
        $this->assertSame(0, PromoCode::count());
        $this->assertSame(1, $tester->execute([
            'user' => '21', 'offer' => 'cart', '--reference' => 'cart-1',
            '--expires-at' => '2026-02-30T12:00:00Z', '--issue' => true,
        ]));
        $this->assertSame(0, PromoCode::count());
        $this->assertSame(0, $tester->execute([
            'user' => '21', 'offer' => 'cart', '--reference' => 'cart-1',
            '--expires-at' => '2026-11-01T12:00:00Z', '--issue' => true,
        ]));
        $this->assertSame(1, PromoCode::count());
    }
}
