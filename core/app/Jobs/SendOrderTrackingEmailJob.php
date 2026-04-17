<?php

namespace App\Jobs;

use App\Helpers\EmailHelper;
use App\Helpers\PriceHelper;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderTrackingEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $orderId;

    public function __construct($orderId)
    {
        $this->orderId = $orderId;
    }

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (
            !$order ||
            !$order->tracking_number ||
            $order->tracking_emailed_at
        ) {
            return;
        }

        $billing = json_decode($order->billing_info, true) ?: [];
        $shipping = json_decode($order->shipping_info, true) ?: [];

        $body = '
            <p>Hello ' . e($billing['bill_first_name'] ?? $shipping['ship_first_name'] ?? 'Customer') . ',</p>
            <p>Your order <strong>' . e($order->transaction_number) . '</strong> has been shipped.</p>
            <p><strong>Tracking number:</strong> ' . e($order->tracking_number) . '</p>
            ' . ($order->shipping_carrier ? '<p><strong>Carrier:</strong> ' . e($order->shipping_carrier) . '</p>' : '') . '
            ' . ($order->shipping_method_name ? '<p><strong>Shipping method:</strong> ' . e($order->shipping_method_name) . '</p>' : '') . '
            <p>You can keep this email for your records and use the tracking number to follow the shipment.</p>
            <p>Thank you,<br>' . e(config('app.name', '99Auto')) . '</p>
        ';

        $emailData = [
            'to' => $billing['bill_email'] ?? $shipping['ship_email'] ?? null,
            'subject' => 'Your order has shipped',
            'body' => $body,
        ];

        if (!$emailData['to']) {
            return;
        }

        $email = new EmailHelper();
        $email->sendCustomMail($emailData);

        $order->tracking_emailed_at = now();
        $order->save();
    }
}
