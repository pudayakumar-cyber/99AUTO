<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\User;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;

class MarketingOfferIssuer
{
    private const OFFERS = [
        'welcome' => ['discount' => 10, 'first_order_only' => true],
        'cart' => ['discount' => 10, 'first_order_only' => false],
        'winback' => ['discount' => 15, 'first_order_only' => false],
    ];

    public function issue(int $userId, string $offer, string $reference, CarbonImmutable $expiresAt): PromoCode
    {
        if (! isset(self::OFFERS[$offer]) || trim($reference) === '' || strlen($reference) > 255) {
            throw new InvalidArgumentException('Use welcome, cart or winback and a non-empty reference of at most 255 bytes.');
        }
        $key = hash('sha256', json_encode([$userId, $offer, trim($reference)], JSON_THROW_ON_ERROR));

        return (new PromoCode)->getConnection()->transaction(function () use ($userId, $offer, $key, $expiresAt) {
            // Serialize retries for this customer; the unique key also enforces idempotency.
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $existing = PromoCode::where('marketing_offer_key', $key)->first();
            if ($existing) {
                // Never renew an expired/redeemed/deactivated offer on retry.
                return $existing;
            }
            $email = strtolower(trim((string) $user->email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $expiresAt->isFuture()) {
                throw new InvalidArgumentException('A valid customer email and future expiry are required.');
            }
            $coupon = new PromoCode(array_merge(self::OFFERS[$offer], [
                'title' => 'Lifecycle '.ucfirst($offer).' offer',
                'code_name' => '99A-'.strtoupper(bin2hex(random_bytes(12))),
                'type' => 'percentage',
                'status' => 1,
                'no_of_times' => 1,
                'recipient_email' => $email,
                'expires_at' => $expiresAt->setTimezone(date_default_timezone_get()),
            ]));
            if (! (new MarketingCouponEligibility)->allows($coupon, $user)) {
                throw new RuntimeException('This customer is not eligible for the offer.');
            }
            $coupon->forceFill(['marketing_offer_key' => $key])->save();

            return $coupon;
        }, 3);
    }
}
