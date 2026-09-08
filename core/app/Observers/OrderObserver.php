<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\CustomerLifecycleService;
use App\Services\KlaviyoOrderEventService;

class OrderObserver
{
    public function __construct(
        private KlaviyoOrderEventService $klaviyo,
        private CustomerLifecycleService $lifecycle
    ) {}

    public function created(Order $order): void
    {
        if ($this->hasFinalOrderNumber($order)) {
            $this->lifecycle->recordOrder($order);
            $this->klaviyo->trackPlacedOrder($order);
        }
    }

    public function updated(Order $order): void
    {
        $placedOrder = $order->wasChanged('transaction_number') && $this->hasFinalOrderNumber($order);
        $orderStatusChanged = $order->wasChanged('order_status');

        if ($placedOrder || ($orderStatusChanged && $this->hasFinalOrderNumber($order))) {
            // Queue profile properties before events that may immediately enter a Klaviyo flow.
            $this->lifecycle->recordOrder($order);
        }

        if ($placedOrder) {
            $this->klaviyo->trackPlacedOrder($order);
        }

        if ($orderStatusChanged) {
            $this->klaviyo->trackOrderStatus($order);
        }

        if ($order->wasChanged('payment_status')) {
            $this->klaviyo->trackRefund($order);
        }

        if ($order->wasChanged('tracking_number')) {
            $this->klaviyo->trackShipment($order);
        }
    }

    private function hasFinalOrderNumber(Order $order): bool
    {
        return str_starts_with((string) $order->transaction_number, 'ORD-');
    }
}
