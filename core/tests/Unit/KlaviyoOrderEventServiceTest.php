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
}
