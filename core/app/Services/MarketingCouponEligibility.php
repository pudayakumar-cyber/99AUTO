<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PromoCode;
use App\Models\User;

class MarketingCouponEligibility
{
    public function allows(PromoCode $coupon, ?User $user): bool
    {
        if ((int) $coupon->status !== 1 || (int) $coupon->no_of_times < 1
            || ($coupon->expires_at !== null && $coupon->expires_at->lte(now()))) {
            return false;
        }

        $recipient = strtolower(trim((string) $coupon->recipient_email));
        if ($recipient !== '' || $coupon->first_order_only) {
            // A checkout email field alone must not unlock another person's offer.
            if (! $user || ! $user->getKey()) {
                return false;
            }
            $email = strtolower(trim((string) $user->email));
            if ($recipient !== '' && $recipient !== $email) {
                return false;
            }
            if ($coupon->first_order_only && Order::query()
                ->where('transaction_number', 'like', 'ORD-%')
                ->where(function ($query) {
                    $query->whereNull('order_status')->orWhere('order_status', '!=', 'Canceled');
                })
                ->where(function ($query) use ($user, $email) {
                    $query->where('user_id', $user->getKey());
                    if ($email !== '') {
                        $emailColumn = $query->getQuery()->getGrammar()->wrap('billing_info->bill_email');
                        $query->orWhereRaw('LOWER('.$emailColumn.') = ?', [$email]);
                    }
                })->exists()) {
                return false;
            }
        }

        return true;
    }
}
