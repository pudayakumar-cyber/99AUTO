<?php

namespace App\Services;

use App\Helpers\PriceHelper;
use App\Models\Order;
use App\Models\Item;
use App\Models\AttributeOption;
use App\Models\TrackOrder;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class StripeOrderCompletion
{
    public function __construct(private StripeCheckoutAttemptStore $attempts) {}

    public function complete(object $intent): Order
    {
        $id = $this->attempts->finalize($intent, function (array $snapshot) use ($intent): int {
            return Order::withoutEvents(function () use ($snapshot, $intent): int {
                $cart = json_decode($snapshot['cart'], true, 512, JSON_THROW_ON_ERROR);
                $billing = json_decode($snapshot['billing_info'], true, 512, JSON_THROW_ON_ERROR);
                $order = Order::create(array_merge($snapshot, ['txnid' => $intent->id, 'payment_status' => 'Paid']));
                $order->transaction_number = 'ORD-'.now()->format('Ymd').'-'.$order->id;
                $order->save();
                // Lock affected inventory before the existing read/modify/write helpers.
                $ids = array_map(fn ($key) => PriceHelper::GetItemId($key), array_keys($cart));
                Item::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                $options = array_merge([], ...array_values(array_map(fn ($item) => $item['options_id'] ?? [], $cart)));
                AttributeOption::whereIn('id', $options)->orderBy('id')->lockForUpdate()->get();
                PriceHelper::Transaction($order->id, $order->transaction_number, $billing['bill_email'] ?? '',
                    $intent->amount / 100, ['sign' => $order->currency_sign, 'value' => $order->currency_value]);
                PriceHelper::stockDecrese($cart);
                PriceHelper::LicenseQtyDecrese($cart);
                $recorded = app(CouponUsageService::class)->record($order);
                if (! $recorded && !empty(json_decode($order->discount, true)['reservation_token'])) {
                    throw new \RuntimeException('Reserved offer could not be finalized.');
                }
                TrackOrder::create(['title' => 'Pending', 'order_id' => $order->id]);
                Notification::create(['order_id' => $order->id]);
                foreach (['klaviyo', 'email', 'sms', 'facebook'] as $topic) {
                    DB::table('stripe_checkout_effects')->insert([
                        'order_id' => $order->id, 'topic' => $topic, 'status' => 'pending',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                return $order->id;
            });
        });
        return Order::findOrFail($id);
    }
}
