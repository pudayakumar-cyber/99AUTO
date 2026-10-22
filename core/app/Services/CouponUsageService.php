<?php

namespace App\Services;

use App\Models\Order;

/** Finalizes reserved personal offers; legacy coupons retain post-order accounting. */
class CouponUsageService
{
    public function record(Order $order): bool
    {
        if (! $order->exists || ! $order->getKey()) {
            return false;
        }

        $connection = $order->getConnection();

        return $connection->transaction(function () use ($connection, $order): bool {
            // Always read persisted data, not a browser session or a stale model.
            $stored = $connection->table('orders')->where('id', $order->getKey())->lockForUpdate()->first();
            if (! $stored || $stored->coupon_usage_recorded_at !== null) {
                return false;
            }

            $discount = json_decode((string) $stored->discount, true);
            $couponId = filter_var(data_get($discount, 'code.id'), FILTER_VALIDATE_INT);
            if ($couponId === false || $couponId < 1) {
                return false;
            }

            $token = data_get($discount, 'reservation_token');
            if (is_string($token) && $token !== '') {
                $reservation = $connection->table('marketing_coupon_reservations')
                    ->where('promo_code_id', $couponId)
                    ->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
                if (! $reservation || (int) $reservation->user_id !== (int) $stored->user_id
                    || ($reservation->order_id !== null && (int) $reservation->order_id !== (int) $stored->id)) {
                    // Never fail an accepted payment or debit another use on replay.
                    return false;
                }
                $connection->table('marketing_coupon_reservations')->where('id', $reservation->id)
                    ->update(['order_id' => $stored->id, 'redeemed_at' => $reservation->redeemed_at ?? now()]);
                $connection->table('orders')->where('id', $stored->id)->update(['coupon_usage_recorded_at' => now()]);
                return true;
            }

            // SQL decrement avoids lost updates between different orders. Never
            // fail an already accepted payment because a coupon was deleted or depleted.
            $changed = $connection->table('promo_codes')->where('id', $couponId)
                ->where('no_of_times', '>', 0)->decrement('no_of_times');
            $connection->table('orders')->where('id', $stored->id)
                ->update(['coupon_usage_recorded_at' => now()]);

            return $changed === 1;
        }, 3);
    }
}
