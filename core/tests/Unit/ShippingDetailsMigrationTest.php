<?php

namespace Tests\Unit;

use App\Models\Order;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class ShippingDetailsMigrationTest extends TestCase
{
    public function test_nullable_details_preserve_existing_orders_and_rollback(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Requires PDO SQLite.');
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
        try {
            $db->schema()->create('orders', function (Blueprint $table) {
                $table->id();
                $table->string('tracking_number')->nullable();
                $table->timestamps();
            });
            $db->table('orders')->insert(['id' => 1, 'tracking_number' => 'ORIGINAL']);
            $migration = require __DIR__.'/../../database/migrations/2026_10_23_000000_add_tracking_details_to_orders.php';
            $migration->up();
            $order = Order::findOrFail(1);
            $this->assertNull($order->tracking_details);
            $this->assertFalse($order->isFillable('tracking_details'));
            $order->forceFill(['tracking_details' => ['tracking_carrier' => 'Example']])->save();
            $this->assertSame('Example', $order->fresh()->tracking_details['tracking_carrier']);
            $migration->down();
            $this->assertFalse($db->schema()->hasColumn('orders', 'tracking_details'));
            $this->assertSame('ORIGINAL', $db->table('orders')->value('tracking_number'));
        } finally {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication(null);
            Container::setInstance(null);
            Model::unsetConnectionResolver();
        }
    }
}
