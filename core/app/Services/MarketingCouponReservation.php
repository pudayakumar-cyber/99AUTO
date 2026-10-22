<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MarketingCouponReservation
{
    public function reserve(PromoCode $snapshot, User $user): string
    {
        $connection = $snapshot->getConnection();
        return $connection->transaction(function () use ($connection, $snapshot, $user) {
            $coupon = PromoCode::whereKey($snapshot->id)->lockForUpdate()->first();
            if (! $coupon || ! $coupon->marketing_offer_key
                || ! (new MarketingCouponEligibility)->allows($coupon, $user)
                || (float) $coupon->discount !== (float) $snapshot->discount || $coupon->type !== $snapshot->type
                || $connection->table('marketing_coupon_reservations')->where('promo_code_id', $coupon->id)->exists()) {
                throw ValidationException::withMessages(['coupon' => 'This personal offer is unavailable or already reserved. Please review your payment status.']);
            }
            $token = bin2hex(random_bytes(32));
            $connection->table('marketing_coupon_reservations')->insert([
                'promo_code_id' => $coupon->id,
                'user_id' => $user->getKey(),
                'token_hash' => hash('sha256', $token),
                'reserved_at' => now(),
            ]);
            // The single use is consumed before any external payment request.
            $connection->table('promo_codes')->where('id', $coupon->id)->update(['no_of_times' => 0]);
            return $token;
        }, 3);
    }

    public function completed(string $token): bool
    {
        return (new PromoCode)->getConnection()->table('marketing_coupon_reservations')
            ->where('token_hash', hash('sha256', $token))->whereNotNull('order_id')->exists();
    }
}
