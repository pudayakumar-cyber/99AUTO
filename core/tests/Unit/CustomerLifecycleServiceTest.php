<?php

namespace Tests\Unit;

use App\Models\CustomerLifecycleOrder;
use App\Models\CustomerLifecycleProfile;
use App\Models\Order;
use App\Services\CustomerLifecycleService;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class CustomerLifecycleServiceTest extends TestCase
{
    private CustomerLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite is required for isolated lifecycle tests.');
        }
        $db = new Capsule;
        $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $db->setAsGlobal();
        $db->bootEloquent();
        $app = $db->getContainer();
        Container::setInstance($app);
        $app->instance('db', $db->getDatabaseManager());
        $app->bind('db.schema', fn () => $db->schema());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        (require __DIR__.'/../../database/migrations/2026_09_05_000000_create_customer_lifecycle_tables.php')->up();
        $db->schema()->create('items', function (Blueprint $table) {
            $table->id();
        });
        Carbon::setTestNow('2026-10-01 12:00:00');
        $this->service = new CustomerLifecycleService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        Model::unsetConnectionResolver();
        parent::tearDown();
    }

    private function order(int $id = 1, string $status = 'Delivered'): Order
    {
        $order = new Order;
        $order->forceFill([
            'id' => $id,
            'transaction_number' => 'ORD-test-'.$id,
            'order_status' => $status,
            'billing_info' => json_encode(['bill_email' => 'lifecycle@example.test']),
            'cart' => '[]',
            'created_at' => '2026-09-01 12:00:00',
        ]);

        return $order;
    }

    public function test_repeated_delivery_updates_and_rebuilds_preserve_first_delivery(): void
    {
        $order = $this->order();
        $this->service->recordOrder($order, false);
        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->service->recordOrder($order, false);

        $record = CustomerLifecycleOrder::sole();
        $this->assertSame('2026-10-01 12:00:00', $record->delivered_at->toDateTimeString());
        $this->assertSame('2026-09-01', $record->purchased_at->toDateString());
        $this->assertSame('2027-02-28', $record->maintenance_due_date->toDateString());
        $this->assertSame(1, CustomerLifecycleProfile::sole()->total_orders);
    }

    public function test_pending_order_gets_delivery_date_only_on_delivery(): void
    {
        $order = $this->order(1, 'Pending');
        $profile = $this->service->recordOrder($order, false);
        $this->assertNull(CustomerLifecycleOrder::sole()->delivered_at);
        $this->assertSame('inactive', $profile->referral_status);

        Carbon::setTestNow('2026-10-05 09:00:00');
        $order->order_status = 'Delivered';
        $profile = $this->service->recordOrder($order, false);
        $this->assertSame('2026-10-05 09:00:00', CustomerLifecycleOrder::sole()->delivered_at->toDateTimeString());
        $this->assertSame('eligible', $profile->referral_status);

        $order->order_status = 'Processing';
        $this->service->recordOrder($order, false);
        $this->assertSame('2026-10-05 09:00:00', CustomerLifecycleOrder::sole()->delivered_at->toDateTimeString());
    }

    public function test_canceling_only_delivered_order_removes_referral_eligibility(): void
    {
        $order = $this->order();
        $this->service->recordOrder($order, false);
        $order->order_status = 'Canceled';
        $profile = $this->service->recordOrder($order, false);

        $this->assertSame(0, CustomerLifecycleOrder::count());
        $this->assertSame('inactive', $profile->referral_status);
        $this->assertSame(0, $profile->total_orders);
        $this->assertNull($profile->next_maintenance_due_date);
    }

    public function test_another_delivered_order_keeps_referral_eligibility(): void
    {
        $order = $this->order();
        $this->service->recordOrder($order, false);
        $this->service->recordOrder($this->order(2), false);
        $order->order_status = 'Canceled';
        $profile = $this->service->recordOrder($order, false);

        $this->assertSame('eligible', $profile->referral_status);
        $this->assertSame(1, $profile->total_orders);
    }
}
