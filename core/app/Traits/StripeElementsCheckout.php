<?php

namespace App\Traits;

use App\{
    Models\Setting,
    Models\TrackOrder,
    Helpers\EmailHelper,
    Helpers\PriceHelper,
    Models\Notification,
    Models\PaymentSetting,
};
use App\Helpers\SmsHelper;
use App\Jobs\EmailSendJob;
use App\Models\Item;
use App\Models\Order;
use App\Models\ShippingService;
use App\Models\State;
use App\Services\OrderMarketingTracker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

trait StripeElementsCheckout
{

    public function __construct()
    {
        $data = PaymentSetting::whereUniqueKeyword('stripe')->first();
        $paydata = $data->convertJsonData();
        Config::set('services.stripe.key', $paydata['key']);
        Config::set('services.stripe.secret', $paydata['secret']);
    }

    /**
     * Create Payment Intent for Stripe Elements
     */
    public function stripeElementsCreateIntent(Request $request)
    {
        if (Session::has('stripe_pending_payment_intent_id')) {
            return response()->json([
                'status' => false,
                'pending' => true,
                'message' => 'Your previous payment is awaiting confirmation. Please contact support before starting another payment.',
            ], 409);
        }
        $user = Auth::user();
        $setting = Setting::first();
        $cart = Session::get('cart');

        $total_tax = 0;
        $cart_total = 0;
        $total = 0;
        $option_price = 0;

        foreach ($cart as $key => $item) {
            $total += $item['main_price'] * $item['qty'];
            $option_price += $item['attribute_price'];
            $cart_total = $total + $option_price;
            $item = Item::findOrFail($key);
            if ($item->tax) {
                $total_tax += $item::taxCalculate($item);
            }
        }

        $discount = [];
        if (Session::has('coupon')) {
            $discount = Session::get('coupon');
        }

        if (!PriceHelper::Digital()) {
            $shipping = null;
        } else {
            $shipping = ShippingService::findOrFail($request->shipping_id);
        }

        $orderData['state'] = $request->state_id ? json_encode(State::findOrFail($request->state_id), true) : null;
        $grand_total = ($cart_total + ($shipping ? $shipping->price : 0)) + $total_tax;
        $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);
        $grand_total += PriceHelper::StatePrce($request->state_id, $cart_total);
        $total_amount = PriceHelper::setConvertPrice($grand_total);

        $orderData['cart'] = json_encode($cart, true);
        $orderData['discount'] = json_encode($discount, true);
        $orderData['shipping'] = json_encode($shipping, true);
        $orderData['tax'] = $total_tax;
        $orderData['state_price'] = PriceHelper::StatePrce($request->state_id, $cart_total);
        $orderData['shipping_info'] = json_encode(Session::get('shipping_address'), true);
        $orderData['billing_info'] = json_encode(Session::get('billing_address'), true);
        $orderData['payment_method'] = 'Stripe';
        $orderData['user_id'] = isset($user) ? $user->id : 0;
        $orderData['transaction_number'] = Str::random(10);
        $orderData['currency_sign'] = PriceHelper::setCurrencySign();
        $orderData['currency_value'] = PriceHelper::setCurrencyValue();
        $orderData['order_status'] = 'Pending';

        \Stripe\Stripe::setApiKey(Config::get('services.stripe.secret'));

        try {
            $attemptId = Session::get('stripe_checkout_attempt_id');
            if (! is_string($attemptId) || ! Str::isUuid($attemptId)) {
                $attemptId = (string) Str::uuid();
                Session::put('stripe_checkout_attempt_id', $attemptId);
            }
            $attempts = app(\App\Services\StripeCheckoutAttemptStore::class);
            $orderData = $attempts->prepare($attemptId, $orderData,
                (int) round($total_amount * 100), strtolower(PriceHelper::setCurrencyName()));
            $paymentIntent = $attempts->provision($attemptId,
                fn (array $parameters, array $options) => \Stripe\PaymentIntent::create($parameters, $options),
                fn (string $id) => \Stripe\PaymentIntent::retrieve($id));

            // Store order data in session for later
            Session::put('order_data', $orderData);
            Session::put('order_input_data', $request->all());
            Session::put('stripe_payment_intent_id', $paymentIntent->id);

            return response()->json([
                'status' => true,
                'clientSecret' => $paymentIntent->client_secret,
                'publishableKey' => Config::get('services.stripe.key'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Confirm payment and create order
     */
    public function stripeElementsConfirm(Request $request)
    {
        try {
            // Prefer payment_intent_id from request (JS POST or 3DS redirect query param)
            // Fall back to session in case it was stored earlier
            $paymentIntentId = $request->input('payment_intent_id')
                ?? $request->get('payment_intent')
                ?? Session::get('stripe_payment_intent_id');

            if (! \App\Support\StripeCheckoutSession::matches(
                $paymentIntentId,
                Session::get('stripe_payment_intent_id'),
                Session::get('order_data'),
                Auth::id()
            )) {
                return response()->json([
                    'status' => false,
                    'message' => 'This payment could not be matched to your checkout session. If you completed payment, contact support before trying again.'
                ], 409);
            }

            // Retrieve the payment intent
            \Stripe\Stripe::setApiKey(Config::get('services.stripe.secret'));
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

            if (in_array($paymentIntent->status, ['processing', 'requires_capture'], true)) {
                Session::put('stripe_pending_payment_intent_id', $paymentIntent->id);
                return response()->json([
                    'status' => false,
                    'pending' => true,
                    'message' => 'Your payment is awaiting confirmation. Do not pay again. Please contact support with reference '.$paymentIntent->id.'.',
                ], 202);
            }

            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment not completed'
                ], 400);
            }

            $existingOrder = Order::where('txnid', $paymentIntent->id)->first();
            if ($existingOrder) {
                \Log::info('Stripe Elements confirm reused existing order.', [
                    'order_id' => $existingOrder->id,
                    'transaction_number' => $existingOrder->transaction_number,
                    'payment_intent_id' => $paymentIntent->id,
                ]);

                Session::put('order_id', $existingOrder->id);
                Session::forget('cart');
                Session::forget('discount');
                Session::forget('order_data');
                Session::forget('order_payment_id');
                Session::forget('coupon');
                Session::forget('stripe_payment_intent_id');
                Session::forget('stripe_pending_payment_intent_id');
                Session::forget('stripe_checkout_attempt_id');

                return response()->json([
                    'status' => true,
                    'redirect' => route('front.checkout.success')
                ]);
            }

            // Use the checkout captured before the provider request.
            $cart = json_decode(Session::get('order_data')['cart'], true);
            $user = Auth::user();
            $orderData = Session::get('order_data');
            $orderData['txnid'] = $paymentIntent->id;
            $orderData['payment_status'] = 'Paid';

            $order = Order::create($orderData);

            $new_txn = 'ORD-' . str_pad(Carbon::now()->format('Ymd'), 4, '0000', STR_PAD_LEFT) . '-' . $order->id;
            $order->transaction_number = $new_txn;
            $order->save();

            // This integration creates Stripe amounts in cents. Use that accepted
            // amount, rather than recalculating from a cart edited during payment.
            $total_amount = $paymentIntent->amount / 100;
            PriceHelper::Transaction($order->id, $order->transaction_number, EmailHelper::getEmail(), $total_amount, [
                'sign' => $order->currency_sign,
                'value' => $order->currency_value,
            ]);
            PriceHelper::stockDecrese($cart);
            PriceHelper::LicenseQtyDecrese($cart);

            app(\App\Services\CouponUsageService::class)->record($order);

            TrackOrder::create([
                'title' => 'Pending',
                'order_id' => $order->id,
            ]);

            Notification::create([
                'order_id' => $order->id
            ]);

            $setting = Setting::first();
            if ($setting->is_twilio == 1) {
                $sms = new SmsHelper();
                $user_number = json_decode($order->billing_info, true)['bill_phone'];
                if ($user_number) {
                    $sms->SendSms($user_number, "'purchase'", $order->transaction_number);
                }
            }

            $emailData = [
                'to' => EmailHelper::getEmail(),
                'type' => "Order",
                'user_name' => isset($user) ? $user->displayName() : Session::get('billing_address')['bill_first_name'],
                'order_cost' => $total_amount,
                'transaction_number' => $order->transaction_number,
                'site_title' => Setting::first()->title,
            ];

            if ($setting->is_queue_enabled == 1) {
                dispatch(new EmailSendJob($emailData, "template"));
            } else {
                $email = new EmailHelper();
                $email->sendTemplateMail($emailData, "template");
            }

            // Send the server-side purchase event early; the success page reuses the same event_id for deduplication.
            try {
                (new OrderMarketingTracker())->trackPurchase($order, $cart);
            } catch (\Throwable $e) {
                \Log::warning('Facebook CAPI tracking failed: ' . $e->getMessage(), [
                    'order_id' => $order->id,
                    'transaction_number' => $order->transaction_number,
                ]);
            }

            Session::put('order_id', $order->id);
            Session::forget('cart');
            Session::forget('discount');
            Session::forget('order_data');
            Session::forget('order_payment_id');
            Session::forget('coupon');
            Session::forget('stripe_payment_intent_id');
            Session::forget('stripe_pending_payment_intent_id');
            Session::forget('stripe_checkout_attempt_id');

            return response()->json([
                'status' => true,
                'redirect' => route('front.checkout.success')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
