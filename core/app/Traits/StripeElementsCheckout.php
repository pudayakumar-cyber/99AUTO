<?php

namespace App\Traits;

use App\Helpers\CheckoutShippingHelper;
use App\{
    Models\Setting,
    Models\PromoCode,
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
            $shipping = CheckoutShippingHelper::orderShippingPayload($request->shipping_id);
        }

        $orderData['state'] = $request->state_id ? json_encode(State::findOrFail($request->state_id), true) : null;
        $grand_total = ($cart_total + ($shipping ? $shipping['price'] : 0)) + $total_tax;
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
            // Create Payment Intent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => (int)($total_amount * 100), // Amount in cents
                'currency' => strtolower(PriceHelper::setCurrencyName()),
                'description' => $setting->title . ' Order',
                'metadata' => [
                    'order_number' => $orderData['transaction_number'],
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

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
        \Stripe\Stripe::setApiKey(Config::get('services.stripe.secret'));

        try {
            $paymentIntentId = Session::get('stripe_payment_intent_id');

            if (!$paymentIntentId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment intent not found'
                ], 400);
            }

            // Retrieve the payment intent
            $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'status' => false,
                    'message' => 'Payment not completed'
                ], 400);
            }

            // Create order
            $cart = Session::get('cart');
            $user = Auth::user();
            $orderData = Session::get('order_data');
            $orderData['txnid'] = $paymentIntent->id;
            $orderData['payment_status'] = 'Paid';

            $order = Order::create($orderData);

            $new_txn = 'ORD-' . str_pad(Carbon::now()->format('Ymd'), 4, '0000', STR_PAD_LEFT) . '-' . $order->id;
            $order->transaction_number = $new_txn;
            $order->save();

            // Calculate total for transaction
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

            $order_input_data = Session::get('order_input_data');
            if (!PriceHelper::Digital()) {
                $shipping = null;
            } else {
                $shipping = CheckoutShippingHelper::orderShippingPayload($order_input_data['shipping_id']);
            }

            $discount = [];
            if (Session::has('coupon')) {
                $discount = Session::get('coupon');
            }

            $grand_total = ($cart_total + ($shipping ? $shipping['price'] : 0)) + $total_tax;
            $grand_total = $grand_total - ($discount ? $discount['discount'] : 0);
            $grand_total += PriceHelper::StatePrce($order_input_data['state_id'], $cart_total);
            $total_amount = PriceHelper::setConvertPrice($grand_total);

            PriceHelper::Transaction($order->id, $order->transaction_number, EmailHelper::getEmail(), PriceHelper::OrderTotal($order, 'trns'));
            PriceHelper::stockDecrese();
            PriceHelper::LicenseQtyDecrese($cart);

            if ($discount) {
                $coupon_id = $discount['code']['id'];
                $get_coupon = PromoCode::findOrFail($coupon_id);
                $get_coupon->no_of_times -= 1;
                $get_coupon->update();
            }

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

            // Send Facebook Conversion API event
            try {
                $facebookApi = new \App\Services\FacebookConversionApi();
                $facebookApi->trackPurchase(
                    $order,
                    $cart,
                    EmailHelper::getEmail(),
                    json_decode($order->billing_info, true)['bill_phone'] ?? null,
                    request()->ip(),
                    request()->header('User-Agent')
                );
            } catch (\Exception $e) {
                \Log::warning('Facebook CAPI tracking failed: ' . $e->getMessage());
            }

            Session::put('order_id', $order->id);
            Session::forget('cart');
            Session::forget('discount');
            Session::forget('order_data');
            Session::forget('order_payment_id');
            Session::forget('coupon');
            Session::forget('stripe_payment_intent_id');

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
