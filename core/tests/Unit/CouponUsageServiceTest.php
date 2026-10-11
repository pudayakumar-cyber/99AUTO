<?php

namespace Tests\Unit;

use App\Http\Controllers\Back\OrderController;
use App\Models\Order;
use App\Services\CouponUsageService;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class CouponUsageServiceTest extends TestCase
{
    private Capsule $db;
    private CouponUsageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite is required for isolated coupon tests.');
        }
        $this->db = new Capsule;
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->setAsGlobal();
        $this->db->bootEloquent();
        $app = $this->db->getContainer();
        Container::setInstance($app);
        $app->instance('db', $this->db->getDatabaseManager());
        $app->bind('db.schema', fn () => $this->db->schema());
        Facade::setFacadeApplication($app);
        $this->db->schema()->create('orders', function (Blueprint $table) {
            $table->id();
            $table->text('discount')->nullable();
            $table->string('transaction_number')->nullable();
            $table->timestamps();
        });
        $this->db->schema()->create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->integer('no_of_times');
        });
        $this->migration()->up();
        $this->db->table('promo_codes')->insert(['id' => 1, 'no_of_times' => 3]);
        $this->service = new CouponUsageService;
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        Order::unsetConnectionResolver();
        parent::tearDown();
    }

    private function migration(): object
    {
        return require __DIR__.'/../../database/migrations/2026_10_08_000000_add_coupon_usage_marker_to_orders.php';
    }

    private function order(?string $discount = null): Order
    {
        return Order::create(['discount' => $discount ?? json_encode(['discount' => 10, 'code' => ['id' => 1]]),
            'transaction_number' => 'ORD-test']);
    }

    private function balance(): int
    {
        return (int) $this->db->table('promo_codes')->where('id', 1)->value('no_of_times');
    }

    public function test_checkout_then_repeated_admin_updates_consume_only_once(): void
    {
        $order = $this->order();
        $staleOrder = Order::findOrFail($order->id);
        $this->assertTrue($this->service->record($order));
        (new OrderController)->setPromoCode($staleOrder);
        (new OrderController)->setPromoCode($order->fresh());
        $this->assertSame(2, $this->balance());
        $this->assertNotNull($order->fresh()->coupon_usage_recorded_at);
    }

    public function test_two_orders_consume_separate_uses_without_negative_balance(): void
    {
        $this->db->table('promo_codes')->update(['no_of_times' => 1]);
        $this->assertTrue($this->service->record($this->order()));
        $second = $this->order();
        $this->assertFalse($this->service->record($second));
        $this->assertSame(0, $this->balance());
        // Replenishing the code must not retroactively debit this order.
        $this->db->table('promo_codes')->update(['no_of_times' => 5]);
        $this->assertFalse($this->service->record($second));
        $this->assertSame(5, $this->balance());
    }

    public function test_persisted_discount_wins_over_stale_model_or_session_data(): void
    {
        $order = $this->order();
        $order->discount = json_encode(['code' => ['id' => 999]]);
        $this->assertTrue($this->service->record($order));
        $this->assertSame(2, $this->balance());
    }

    public function test_missing_deleted_and_malformed_coupons_do_not_break_order_handling(): void
    {
        foreach (['[]', 'null', 'broken json', '{"code":{"id":-1}}', '{"code":{"id":"x"}}', '{"code":{"id":999}}'] as $discount) {
            $this->assertFalse($this->service->record($this->order($discount)));
        }
        $this->assertFalse($this->service->record(new Order));
        $this->assertSame(3, $this->balance());
    }

    public function test_failed_marker_update_rolls_back_the_coupon_decrement(): void
    {
        $order = $this->order();
        $this->db->getConnection()->statement("CREATE TRIGGER reject_marker BEFORE UPDATE OF coupon_usage_recorded_at ON orders BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        try {
            $this->service->record($order);
            $this->fail('Expected marker update failure.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('test failure', $exception->getMessage());
        }
        $this->assertSame(3, $this->balance());
        $this->assertNull($order->fresh()->coupon_usage_recorded_at);
    }

    public function test_migration_preserves_historical_balances_and_leaves_drafts_unmarked(): void
    {
        $this->migration()->down();
        $historical = $this->order();
        $draft = $this->order();
        $draft->update(['transaction_number' => 'temporary-checkout']);
        $this->migration()->up();
        $this->assertNotNull($historical->fresh()->coupon_usage_recorded_at);
        $this->assertNull($draft->fresh()->coupon_usage_recorded_at);
        $this->assertFalse($this->service->record($historical));
        $this->assertSame(3, $this->balance());
        $this->assertTrue($this->service->record($draft));
        $this->assertSame(2, $this->balance());
    }
}
