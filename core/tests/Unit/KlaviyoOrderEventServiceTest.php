<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\KlaviyoOrderEventService;
use Carbon\Carbon;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\UrlGenerator;
use PHPUnit\Framework\TestCase;

class KlaviyoOrderEventServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container;
        Container::setInstance($app);
        $app->instance('config', new Repository(['app' => ['url' => 'https://99autoparts.ca']]));
        $url = new class {
            public function to($path): string
            {
                return 'https://99autoparts.ca/'.ltrim($path, '/');
            }
        };
        $app->instance('url', $url);
        $app->instance(UrlGenerator::class, $url);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_it_builds_one_placed_order_and_one_event_per_line_item(): void
    {
        $events = (new KlaviyoOrderEventService)->placedOrderEvents($this->order());

        $this->assertCount(3, $events);
        $this->assertSame('Placed Order', $events[0]['metric_name']);
        $this->assertSame('klaviyo-placed-order-42', $events[0]['unique_id']);
        $this->assertSame(64.50, $events[0]['value']);
        $this->assertSame('customer@example.com', $events[0]['profile']['email']);
        $this->assertSame('+14165550123', $events[0]['profile']['phone_number']);
        $this->assertSame('Ordered Product', $events[1]['metric_name']);
        $this->assertSame(2, $events[1]['properties']['Quantity']);
        $this->assertSame(42.00, $events[1]['properties']['RowTotal']);
    }

    public function test_status_events_use_stable_order_ids(): void
    {
        $order = $this->order();
        $order->order_status = 'Delivered';

        $event = (new KlaviyoOrderEventService)->orderEvent($order, 'Fulfilled Order');

        $this->assertSame('klaviyo-fulfilled-order-42', $event['unique_id']);
        $this->assertSame('Delivered', $event['properties']['FulfillmentStatus']);
    }

    public function test_shipping_events_include_only_supplied_details(): void
    {
        $order = $this->order();
        $service = new KlaviyoOrderEventService;
        $withoutDetails = $service->orderEvent($order, 'Shipped Order')['properties'];
        $this->assertArrayNotHasKey('TrackingURL', $withoutDetails);
        $this->assertArrayNotHasKey('EstimatedDeliveryDate', $withoutDetails);
        $order->tracking_details = [
            'tracking_carrier' => 'Test Carrier',
            'tracking_url' => 'https://carrier.example/track/123',
            'estimated_delivery_date' => '2026-10-25',
        ];
        $properties = $service->orderEvent($order, 'Shipped Order')['properties'];
        $this->assertSame('Test Carrier', $properties['Carrier']);
        $this->assertSame('https://carrier.example/track/123', $properties['TrackingURL']);
        $this->assertSame('2026-10-25', $properties['EstimatedDeliveryDate']);
    }

    public function test_out_for_delivery_requires_confirmation_and_uses_stable_shipment_identity(): void
    {
        config(['services.klaviyo.enabled' => true]);
        $jobs = [];
        $bus = \Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
        $bus->shouldReceive('dispatch')->twice()->andReturnUsing(function ($job) use (&$jobs) {
            $jobs[] = $job;
        });
        Container::getInstance()->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $bus);
        $order = $this->order();
        $service = new KlaviyoOrderEventService;
        $service->trackOutForDelivery($order);
        $order->tracking_number = 'TRACK-42';
        $service->trackOutForDelivery($order);
        $order->tracking_details = ['out_for_delivery_at' => '2026-10-22T12:00:00Z'];
        $service->trackOutForDelivery($order);
        $service->trackOutForDelivery($order);
        foreach (['Canceled', 'Delivered'] as $status) {
            $order->order_status = $status;
            $service->trackOutForDelivery($order);
        }
        $order->order_status = 'Pending';
        $order->transaction_number = 'temporary';
        $service->trackOutForDelivery($order);
        $this->assertCount(2, $jobs);
        $this->assertSame($jobs[0]->uniqueId, $jobs[1]->uniqueId);
        $this->assertSame('Out for Delivery', $jobs[0]->metricName);
        \Mockery::close();
    }

    private function order(): Order
    {
        $order = new Order;
        $order->setRawAttributes([
            'id' => 42,
            'user_id' => 0,
            'transaction_number' => 'ORD-20260903-42',
            'order_status' => 'Pending',
            'payment_status' => 'Paid',
            'payment_method' => 'Stripe',
            'currency_sign' => 'CAD',
            'currency_value' => 1,
            'tax' => 3,
            'state_price' => 2,
            'shipping' => json_encode(['title' => 'Standard', 'price' => 10]),
            'discount' => json_encode(['discount' => 5]),
            'billing_info' => json_encode([
                'bill_email' => 'CUSTOMER@example.com',
                'bill_phone' => '(416) 555-0123',
                'bill_first_name' => 'Pat',
                'bill_last_name' => 'Lee',
                'bill_city' => 'Toronto',
                'bill_country' => 'Canada',
                'bill_zip' => 'M5V 1A1',
            ]),
            'cart' => json_encode([
                '10-' => [
                    'id' => 10,
                    'name' => 'Brake Pad',
                    'slug' => 'brake-pad',
                    'qty' => 2,
                    'main_price' => 20,
                    'attribute_price' => 1,
                    'photo' => 'pad.jpg',
                ],
                '11-' => [
                    'id' => 11,
                    'name' => 'Rotor',
                    'slug' => 'rotor',
                    'qty' => 1,
                    'main_price' => 12.5,
                    'attribute_price' => 0,
                ],
            ]),
            'created_at' => Carbon::parse('2026-09-03T12:00:00Z'),
        ]);

        return $order;
    }

    public function test_delivered_products_keep_independent_intervals_and_stable_ids(): void
    {
        $order = $this->order();
        $order->order_status = 'Delivered';
        $cart = json_decode($order->cart, true);
        $cart['11-']['name'] = 'Engine Oil';
        $cart['12-'] = ['id' => 12, 'name' => 'Oil Filter', 'slug' => 'oil-filter', 'qty' => 1];
        $order->cart = json_encode($cart);
        $service = new KlaviyoOrderEventService;
        $events = $service->deliveredProductEvents($order, '2026-10-23T12:00:00Z');
        $this->assertCount(3, $events);
        $this->assertSame([365, 90, 180], array_column(array_column($events, 'properties'), 'MaintenanceIntervalDays'));
        $this->assertSame('2027-01-21', $events[1]['properties']['MaintenanceDueDate']);
        $this->assertSame('12', $events[2]['properties']['ProductID']);
        $this->assertSame('https://99autoparts.ca/product/oil-filter?item_id=12', $events[2]['properties']['ProductURL']);
        $this->assertCount(3, array_unique(array_column($events, 'unique_id')));
        $this->assertSame($events, $service->deliveredProductEvents($order, '2026-10-23T12:00:00Z'));
        $order->id = 43;
        $this->assertNotSame($events[0]['unique_id'], $service->deliveredProductEvents($order, '2026-10-24T12:00:00Z')[0]['unique_id']);
    }

    public function test_undelivered_temporary_and_refunded_orders_do_not_start_reminders(): void
    {
        $order = $this->order();
        $service = new KlaviyoOrderEventService;
        $this->assertSame([], $service->deliveredProductEvents($order, '2026-10-23'));
        $order->order_status = 'Delivered';
        $order->transaction_number = 'temporary';
        $this->assertSame([], $service->deliveredProductEvents($order, '2026-10-23'));
        $order->transaction_number = 'ORD-42';
        $order->payment_status = 'Refunded';
        $this->assertSame([], $service->deliveredProductEvents($order, '2026-10-23'));
    }

    public function test_worker_product_and_image_urls_do_not_inherit_core_prefix(): void
    {
        config(['app.url' => 'https://99autoparts.ca/core/']);
        $events = (new KlaviyoOrderEventService)->placedOrderEvents($this->order());
        $this->assertSame('https://99autoparts.ca/product/brake-pad?item_id=10', $events[1]['properties']['ProductURL']);
        $this->assertSame('https://99autoparts.ca/core/public/storage/images/pad.jpg', $events[1]['properties']['ImageURL']);
    }
}
