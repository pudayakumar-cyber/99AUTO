<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Helpers\EmailHelper;
use App\Helpers\SmsHelper;
use Illuminate\Support\Facades\DB;

class StripeCheckoutEffects
{
    public function process(int $orderId): void
    {
        foreach (DB::table('stripe_checkout_effects')->where('order_id', $orderId)->where('status', 'pending')->get() as $effect) {
            // Claim before external side effects: an uncertain outcome is never blindly retried.
            if (!DB::table('stripe_checkout_effects')->where('id', $effect->id)->where('status', 'pending')
                ->update(['status' => 'claimed', 'updated_at' => now()])) {
                continue;
            }
            try {
                $order = Order::findOrFail($orderId);
                $billing = json_decode($order->billing_info, true) ?: [];
                $setting = Setting::firstOrFail();
                if ($effect->topic === 'klaviyo') {
                    app(CustomerLifecycleService::class)->recordOrder($order);
                    app(KlaviyoOrderEventService::class)->trackPlacedOrder($order);
                } elseif ($effect->topic === 'email') {
                    app(EmailHelper::class)->sendTemplateMail([
                        'to' => $billing['bill_email'] ?? '', 'type' => 'Order',
                        'user_name' => trim(($billing['bill_first_name'] ?? '').' '.($billing['bill_last_name'] ?? '')),
                        'order_cost' => DB::table('stripe_checkout_attempts')->where('order_id', $orderId)->value('amount') / 100,
                        'transaction_number' => $order->transaction_number, 'site_title' => $setting->title,
                    ], 'template', true);
                } elseif ($effect->topic === 'sms' && $setting->is_twilio == 1 && !empty($billing['bill_phone'])) {
                    app(SmsHelper::class)->SendSms($billing['bill_phone'], "'purchase'", $order->transaction_number, true);
                } elseif ($effect->topic === 'facebook') {
                    app(OrderMarketingTracker::class)->trackPurchase($order);
                }
                DB::table('stripe_checkout_effects')->where('id', $effect->id)->update(['status' => 'done', 'updated_at' => now()]);
            } catch (\Throwable $e) {
                DB::table('stripe_checkout_effects')->where('id', $effect->id)->update(['status' => 'review', 'updated_at' => now()]);
                \Illuminate\Support\Facades\Log::warning('Stripe order effect requires review.', ['effect_id' => $effect->id, 'exception' => $e::class]);
            }
        }
    }
}
