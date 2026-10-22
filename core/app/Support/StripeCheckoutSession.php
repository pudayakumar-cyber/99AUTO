<?php

namespace App\Support;

/** Bind a browser confirmation to the checkout prepared by this server session. */
class StripeCheckoutSession
{
    public static function matches(mixed $requestedId, mixed $expectedId, mixed $order, mixed $userId): bool
    {
        if (! is_string($requestedId) || $requestedId === '' || ! is_string($expectedId)
            || ! hash_equals($expectedId, $requestedId) || ! is_array($order)
            || ! array_key_exists('user_id', $order)) {
            return false;
        }

        $owner = filter_var($order['user_id'], FILTER_VALIDATE_INT);
        $current = filter_var($userId ?? 0, FILTER_VALIDATE_INT);
        if ($owner === false || $current === false || $owner < 0 || $owner !== $current) {
            return false;
        }

        $cart = is_string($order['cart'] ?? null) ? json_decode($order['cart'], true) : null;
        return is_array($cart) && $cart !== [];
    }
}
