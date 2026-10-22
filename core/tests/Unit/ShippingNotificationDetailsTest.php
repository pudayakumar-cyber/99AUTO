<?php

namespace Tests\Unit;

use App\Support\ShippingNotificationDetails;
use Carbon\Carbon;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;

class ShippingNotificationDetailsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_invalid_links_dates_and_blank_tracking_are_rejected(): void
    {
        $validator = new Factory(new Translator(new ArrayLoader, 'en'));
        foreach ([
            ['tracking_number' => '   '],
            ['tracking_number' => 'ABC', 'tracking_url' => 'javascript:alert(1)'],
            ['tracking_number' => 'ABC', 'tracking_url' => 'http://carrier.example/track'],
            ['tracking_number' => 'ABC', 'estimated_delivery_date' => '2026-02-30'],
            ['tracking_number' => 'ABC', 'out_for_delivery' => 'yes'],
        ] as $input) {
            $this->assertTrue($validator->make($input, ShippingNotificationDetails::rules())->fails());
        }
        $this->assertTrue($validator->make(['tracking_number' => 'ABC'], ShippingNotificationDetails::rules())->passes());
    }

    public function test_tracking_replacement_does_not_inherit_previous_confirmation(): void
    {
        $old = ['tracking_carrier' => 'Old carrier', 'out_for_delivery_at' => '2026-10-01T12:00:00Z'];
        $this->assertSame([], ShippingNotificationDetails::fromInput([], $old, true));
        $this->assertSame($old, ShippingNotificationDetails::fromInput([], $old, false));
    }

    public function test_confirmation_timestamp_is_stable_and_can_be_cleared(): void
    {
        Carbon::setTestNow('2026-10-22 12:00:00');
        $first = ShippingNotificationDetails::fromInput(['out_for_delivery' => '1'], [], false);
        Carbon::setTestNow('2026-10-23 12:00:00');
        $this->assertSame($first, ShippingNotificationDetails::fromInput(['out_for_delivery' => '1'], $first, false));
        $this->assertNull(ShippingNotificationDetails::fromInput(['out_for_delivery' => '0'], $first, false)['out_for_delivery_at']);
    }

    public function test_unsafe_imported_urls_are_never_added_to_events(): void
    {
        foreach (['javascript:alert(1)', 'https://secret@carrier.example/track', 'http://carrier.example'] as $url) {
            $this->assertArrayNotHasKey('TrackingURL', ShippingNotificationDetails::eventProperties(['tracking_url' => $url]));
        }
    }
}
