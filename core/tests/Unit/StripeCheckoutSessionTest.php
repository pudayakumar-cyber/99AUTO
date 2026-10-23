<?php

namespace Tests\Unit;

use App\Http\Controllers\Front\CheckoutController;
use App\Support\StripeCheckoutSession;
use Illuminate\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class StripeCheckoutSessionTest extends TestCase
{
    private Store $session;
    private CheckoutController $controller;

    protected function setUp(): void
    {
        $app = new Container;
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        $this->session = new Store('stripe-test', new ArraySessionHandler(120));
        $app->instance('session', $this->session);
        $app->instance('auth', new class { public function id() { return 21; } });
        $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, new class {
            public function json($data, $status) { return new JsonResponse($data, $status); }
        });
        $app->instance('redirect', new class {
            public function route($route) { return new RedirectResponse('/'.$route); }
        });
        // Skip the production controller constructor's payment/settings DB reads.
        $this->controller = new class extends CheckoutController { public function __construct() {} };
        $this->session->put('stripe_payment_intent_id', 'pi_expected');
        $this->session->put('order_data', ['user_id' => 21, 'cart' => '{"107955":{"qty":1}}']);
    }

    protected function tearDown(): void
    {
        \Stripe\ApiRequestor::setHttpClient(null);
        \Stripe\Stripe::setApiKey(null);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_matching_member_and_guest_sessions_are_allowed(): void
    {
        $order = $this->session->get('order_data');
        $this->assertTrue(StripeCheckoutSession::matches('pi_expected', 'pi_expected', $order, 21));
        $order['user_id'] = 0;
        $this->assertTrue(StripeCheckoutSession::matches('pi_expected', 'pi_expected', $order, null));
    }

    public function test_expired_switched_and_malformed_sessions_are_rejected(): void
    {
        $order = $this->session->get('order_data');
        foreach ([
            ['pi_other', 'pi_expected', $order, 21],
            ['pi_expected', null, $order, 21],
            ['pi_expected', 'pi_expected', null, 21],
            ['pi_expected', 'pi_expected', $order, 22],
            ['pi_expected', 'pi_expected', $order, null],
            [['pi_expected'], 'pi_expected', $order, 21],
            ['pi_expected', 'pi_expected', ['user_id' => 21, 'cart' => 'invalid'], 21],
            ['pi_expected', 'pi_expected', ['user_id' => 21, 'cart' => '[]'], 21],
        ] as $args) {
            $this->assertFalse(StripeCheckoutSession::matches(...$args));
        }
    }

    public function test_post_rejects_another_intent_without_touching_checkout(): void
    {
        $snapshot = $this->session->all();
        $response = $this->controller->stripeConfirmPayment(Request::create('/stripe/confirm-payment', 'POST', [
            'payment_intent_id' => 'pi_other',
        ]));
        $this->assertSame(409, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['status']);
        $this->assertSame($snapshot, $this->session->all());
    }

    public function test_redirect_cannot_replace_server_created_intent(): void
    {
        $response = $this->controller->stripeReturn(Request::create('/stripe/return', 'GET', [
            'payment_intent' => 'pi_other', 'redirect_status' => 'succeeded',
        ]));
        $this->assertSame('/front.checkout.cancle', $response->getTargetUrl());
        $this->assertSame('pi_expected', $this->session->get('stripe_payment_intent_id'));
        $this->assertSame(21, $this->session->get('order_data.user_id'));
        $this->assertStringContainsString('could not be matched', $this->session->get('message'));
    }

    public function test_missing_snapshot_stops_confirmation_before_provider_or_order_access(): void
    {
        $this->session->forget('order_data');
        $response = $this->controller->stripeConfirmPayment(Request::create('/stripe/confirm-payment', 'POST', [
            'payment_intent_id' => 'pi_expected',
        ]));
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('pi_expected', $this->session->get('stripe_payment_intent_id'));
    }

    public function test_matching_session_reaches_provider_for_post_redirect_and_session_fallback(): void
    {
        Container::getInstance()->instance('config', new \Illuminate\Config\Repository([
            'services' => ['stripe' => ['secret' => 'sk_test_fake_unit_test_only']],
        ]));
        $client = new class implements \Stripe\HttpClient\ClientInterface {
            public array $urls = [];
            public function request($method, $absUrl, $headers, $params, $hasFile)
            {
                $this->urls[] = $absUrl;
                return ['{"id":"pi_expected","object":"payment_intent","status":"requires_payment_method"}', 200, []];
            }
        };
        \Stripe\ApiRequestor::setHttpClient($client);
        foreach ([['payment_intent_id' => 'pi_expected'], ['payment_intent' => 'pi_expected'], []] as $input) {
            $response = $this->controller->stripeConfirmPayment(Request::create('/stripe/confirm-payment', 'POST', $input));
            $this->assertSame(400, $response->getStatusCode());
            $this->assertSame('Payment not completed', $response->getData(true)['message']);
        }
        $this->assertCount(3, $client->urls);
        foreach ($client->urls as $url) {
            $this->assertStringEndsWith('/payment_intents/pi_expected', $url);
        }
    }

    public function test_pending_payments_preserve_checkout_without_creating_paid_orders(): void
    {
        Container::getInstance()->instance('config', new \Illuminate\Config\Repository([
            'services' => ['stripe' => ['secret' => 'sk_test_fake_unit_test_only']],
        ]));
        $client = new class implements \Stripe\HttpClient\ClientInterface {
            public string $status = 'processing';
            public function request($method, $url, $headers, $params, $hasFile)
            {
                return [json_encode(['id' => 'pi_expected', 'object' => 'payment_intent', 'status' => $this->status]), 200, []];
            }
        };
        \Stripe\ApiRequestor::setHttpClient($client);
        $snapshot = $this->session->get('order_data');
        foreach (['processing', 'requires_capture'] as $status) {
            $client->status = $status;
            // There is no database in this test: reaching order creation would fail.
            $response = $this->controller->stripeConfirmPayment(Request::create('/stripe/confirm-payment', 'POST'));
            $this->assertSame(202, $response->getStatusCode());
            $this->assertTrue($response->getData(true)['pending']);
            $this->assertFalse($response->getData(true)['status']);
            $this->assertSame($snapshot, $this->session->get('order_data'));
            $this->assertSame('pi_expected', $this->session->get('stripe_pending_payment_intent_id'));
            $blocked = $this->controller->stripeCreateIntent(Request::create('/stripe/create-intent', 'POST'));
            $this->assertSame(409, $blocked->getStatusCode());
            $this->assertTrue($blocked->getData(true)['pending']);
        }
        $client->status = 'processing';
        $redirect = $this->controller->stripeReturn(Request::create('/stripe/return', 'GET', [
            'payment_intent' => 'pi_expected', 'redirect_status' => 'processing',
        ]));
        $this->assertStringContainsString('Do not pay again', $this->session->get('message'));
        $this->assertSame($snapshot, $this->session->get('order_data'));
    }
}
