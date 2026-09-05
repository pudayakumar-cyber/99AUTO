<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Observers\OrderObserver;
use App\Services\CustomerLifecycleService;
use App\Services\KlaviyoOrderEventService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OrderObserverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_created_order_is_tracked_only_with_a_final_order_number(): void
    {
        $service = Mockery::mock(KlaviyoOrderEventService::class);
        $lifecycle = Mockery::mock(CustomerLifecycleService::class);
        $observer = new OrderObserver($service, $lifecycle);
        $draft = $this->order('temporary-reference');
        $final = $this->order('ORD-20260903-42');

        $service->shouldReceive('trackPlacedOrder')->once()->with($final);
        $lifecycle->shouldReceive('recordOrder')->once()->with($final);

        $observer->created($draft);
        $observer->created($final);

        $this->addToAssertionCount(1);
    }

    public function test_updated_order_dispatches_only_changed_lifecycle_events(): void
    {
        $service = Mockery::mock(KlaviyoOrderEventService::class);
        $lifecycle = Mockery::mock(CustomerLifecycleService::class);
        $observer = new OrderObserver($service, $lifecycle);
        $order = Mockery::mock(Order::class)->makePartial();
        $order->transaction_number = 'ORD-20260903-42';
        $order->order_status = 'Delivered';
        $order->payment_status = 'Paid';
        $order->tracking_number = 'TRACK-42';
        $order->shouldReceive('wasChanged')->with('transaction_number')->andReturnTrue();
        $order->shouldReceive('wasChanged')->with('order_status')->andReturnTrue();
        $order->shouldReceive('wasChanged')->with('payment_status')->andReturnFalse();
        $order->shouldReceive('wasChanged')->with('tracking_number')->andReturnTrue();

        $service->shouldReceive('trackPlacedOrder')->once()->with($order);
        $service->shouldReceive('trackOrderStatus')->once()->with($order);
        $service->shouldNotReceive('trackRefund');
        $service->shouldReceive('trackShipment')->once()->with($order);
        $lifecycle->shouldReceive('recordOrder')->once()->with($order);

        $observer->updated($order);

        $this->addToAssertionCount(1);
    }

    private function order(string $number): Order
    {
        $order = new Order;
        $order->setRawAttributes(['id' => 42, 'transaction_number' => $number]);

        return $order;
    }
}
