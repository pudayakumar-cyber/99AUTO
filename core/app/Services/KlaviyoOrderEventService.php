<?php

namespace App\Services;

use App\Jobs\SendKlaviyoEvent;
use App\Models\Order;
use App\Support\MarketingIdentity;
use App\Support\StorefrontImage;
use DateTimeInterface;
use Throwable;

class KlaviyoOrderEventService
{
    public function trackPlacedOrder(Order $order): void
    {
        foreach ($this->placedOrderEvents($order) as $event) {
            $this->dispatch($event);
        }
    }

    public function trackOrderStatus(Order $order): void
    {
        $metric = match ($order->order_status) {
            'Delivered' => 'Fulfilled Order',
            'Canceled' => 'Canceled Order',
            default => null,
        };

        if ($metric !== null) {
            $this->dispatch($this->orderEvent($order, $metric));
        }
    }

    public function trackRefund(Order $order): void
    {
        if ($order->payment_status === 'Refunded') {
            $this->dispatch($this->orderEvent($order, 'Refunded Order'));
        }
    }

    public function trackShipment(Order $order): void
    {
        $trackingNumber = trim((string) $order->tracking_number);

        if ($trackingNumber === '') {
            return;
        }

        $event = $this->orderEvent($order, 'Shipped Order');
        $event['properties']['TrackingNumber'] = $trackingNumber;
        $event['unique_id'] .= '-'.sha1($trackingNumber);

        $this->dispatch($event);
    }

    public function placedOrderEvents(Order $order): array
    {
        $orderEvent = $this->orderEvent($order, 'Placed Order', $this->orderCreatedAt($order));
        $events = [$orderEvent];

        foreach ($this->items($order) as $item) {
            $events[] = [
                'metric_name' => 'Ordered Product',
                'profile' => $orderEvent['profile'],
                'properties' => array_merge($this->orderSummary($order), $item),
                'unique_id' => 'klaviyo-ordered-product-'.$order->id.'-'.$item['LineID'],
                'occurred_at' => $orderEvent['occurred_at'],
                'value' => $item['RowTotal'],
            ];
        }

        return $events;
    }

    public function orderEvent(Order $order, string $metric, ?string $occurredAt = null): array
    {
        $items = $this->items($order);

        return [
            'metric_name' => $metric,
            'profile' => $this->profile($order),
            'properties' => array_merge($this->orderSummary($order), [
                'ItemNames' => array_values(array_map(fn (array $item) => $item['ProductName'], $items)),
                'Items' => $items,
                'ItemCount' => array_sum(array_column($items, 'Quantity')),
            ]),
            'unique_id' => 'klaviyo-'.strtolower(str_replace(' ', '-', $metric)).'-'.$order->id,
            'occurred_at' => $occurredAt ?: now()->toAtomString(),
            'value' => $this->orderValue($order),
        ];
    }

    private function dispatch(array $event): void
    {
        if (! config('services.klaviyo.enabled')) {
            return;
        }

        SendKlaviyoEvent::dispatch(
            $event['metric_name'],
            $event['profile'],
            $event['properties'],
            $event['unique_id'],
            $event['occurred_at'],
            $event['value']
        );
    }

    private function profile(Order $order): array
    {
        $billing = $this->decode($order->billing_info);
        $shipping = $this->decode($order->shipping_info);
        $state = $this->decode($order->state);
        $user = $order->user_id ? $order->user : null;

        $email = strtolower(trim((string) ($billing['bill_email'] ?? $shipping['ship_email'] ?? $user?->email ?? '')));
        $phone = $this->phone((string) ($billing['bill_phone'] ?? $shipping['ship_phone'] ?? $user?->phone ?? ''));
        $firstName = trim((string) ($billing['bill_first_name'] ?? $shipping['ship_first_name'] ?? $user?->first_name ?? ''));
        $lastName = trim((string) ($billing['bill_last_name'] ?? $shipping['ship_last_name'] ?? $user?->last_name ?? ''));

        return array_filter([
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'phone_number' => $phone,
            'external_id' => $order->user_id ? (string) $order->user_id : null,
            'first_name' => $firstName ?: null,
            'last_name' => $lastName ?: null,
            'location' => array_filter([
                'address1' => $billing['bill_address1'] ?? $shipping['ship_address1'] ?? null,
                'address2' => $billing['bill_address2'] ?? $shipping['ship_address2'] ?? null,
                'city' => $billing['bill_city'] ?? $shipping['ship_city'] ?? null,
                'region' => $billing['bill_state'] ?? $shipping['ship_state'] ?? $state['name'] ?? null,
                'country' => $billing['bill_country'] ?? $shipping['ship_country'] ?? null,
                'zip' => $billing['bill_zip'] ?? $shipping['ship_zip'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    private function phone(string $phone): ?string
    {
        if (trim($phone) === '') {
            return null;
        }

        try {
            return MarketingIdentity::phone($phone);
        } catch (Throwable) {
            return null;
        }
    }

    private function orderSummary(Order $order): array
    {
        $shipping = $this->decode($order->shipping);
        $discount = $this->decode($order->discount);

        return array_filter([
            'OrderID' => (string) $order->id,
            'OrderNumber' => $order->transaction_number,
            'PaymentStatus' => $order->payment_status,
            'FulfillmentStatus' => $order->order_status,
            'PaymentMethod' => $order->payment_method,
            'Currency' => $this->currency($order),
            'Value' => $this->orderValue($order),
            'ShippingMethod' => $shipping['title'] ?? $shipping['name'] ?? null,
            'DiscountValue' => isset($discount['discount']) ? (float) $discount['discount'] : null,
            'TrackingNumber' => trim((string) $order->tracking_number) ?: null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function items(Order $order): array
    {
        $items = [];

        foreach ($this->decode($order->cart) as $key => $cartItem) {
            $quantity = max(1, (int) ($cartItem['qty'] ?? 1));
            $itemPrice = (float) ($cartItem['main_price'] ?? $cartItem['price'] ?? 0)
                + (float) ($cartItem['attribute_price'] ?? 0);
            $productId = (string) ($cartItem['id'] ?? $cartItem['item_id'] ?? explode('-', (string) $key)[0]);
            $slug = trim((string) ($cartItem['slug'] ?? ''));

            $items[] = array_filter([
                'LineID' => sha1((string) $key),
                'ProductID' => $productId,
                'ProductName' => (string) ($cartItem['name'] ?? ''),
                'Quantity' => $quantity,
                'ItemPrice' => round($itemPrice, 2),
                'RowTotal' => round($itemPrice * $quantity, 2),
                'ProductURL' => $slug !== '' ? url('/product/'.$slug.'?item_id='.$productId) : null,
                'ImageURL' => StorefrontImage::url($cartItem['photo'] ?? null),
            ], fn ($value) => $value !== null && $value !== '');
        }

        return $items;
    }

    private function orderValue(Order $order): float
    {
        $itemsTotal = array_sum(array_column($this->items($order), 'RowTotal'));
        $shipping = $this->decode($order->shipping);
        $discount = $this->decode($order->discount);
        $value = $itemsTotal
            + (float) ($shipping['price'] ?? 0)
            + (float) $order->tax
            + (float) $order->state_price
            - (float) ($discount['discount'] ?? 0);

        return round(max(0, $value * ((float) $order->currency_value ?: 1)), 2);
    }

    private function currency(Order $order): string
    {
        $currency = strtoupper(trim((string) $order->currency_sign));

        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'CAD';
    }

    private function orderCreatedAt(Order $order): string
    {
        $date = $order->created_at;

        return $date instanceof DateTimeInterface ? $date->format(DateTimeInterface::ATOM) : now()->toAtomString();
    }

    private function decode($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true) ?: [];
    }
}
