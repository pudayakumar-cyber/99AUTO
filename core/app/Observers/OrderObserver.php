<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\KlaviyoOrderEventService;

class OrderObserver
{
    public function __construct(private KlaviyoOrderEventService $klaviyo)
    {
    }

    public function created(Order $order): void
    {
        if ($this->hasFinalOrderNumber($order)) {
            $this->klaviyo->trackPlacedOrder($order);
        }
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('transaction_number') && $this->hasFinalOrderNumber($order)) {
            $this->klaviyo->trackPlacedOrder($order);
        }

        if ($order->wasChanged('order_status')) {
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
