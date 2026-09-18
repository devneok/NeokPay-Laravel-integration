<?php

declare(strict_types=1);

namespace Neok\Pay\Laravel\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Neok\Pay\Client;
use Neok\Pay\ClientConfig;
use Neok\Pay\DTO\CreateCheckoutRequest;
use Neok\Pay\Laravel\Events\PaymentSucceeded;
use Neok\Pay\Laravel\Events\UnknownWebhookReceived;
use Neok\Pay\Laravel\Facades\NeokPay;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class IntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $config = sys_get_temp_dir().'/neokpay-config-'.bin2hex(random_bytes(8));
        mkdir($config);
        $app->useConfigPath($config);
    }

    public function test_bindings_and_facade_share_core_client(): void
    {
        self::assertInstanceOf(Client::class, app(Client::class));
        self::assertSame(app(Client::class), NeokPay::getFacadeRoot());
        self::assertInstanceOf(ClientConfig::class, app(ClientConfig::class));
        self::assertInstanceOf(GuzzleClient::class, app(ClientInterface::class));
        self::assertInstanceOf(RequestFactoryInterface::class, app(RequestFactoryInterface::class));
        self::assertInstanceOf(StreamFactoryInterface::class, app(StreamFactoryInterface::class));
    }

    public function test_default_config_merges_and_overrides_are_used(): void
    {
        self::assertSame('https://pay.neok.me/api/v1', config('neokpay.base_url'));
        self::assertSame(300, config('neokpay.webhook.timestamp_tolerance'));
        self::assertSame('neokpay/webhook', config('neokpay.webhook.path'));
        config(['neokpay.base_url' => 'https://fake.test/api/v1']);
        self::assertSame('https://fake.test/api/v1', app(ClientConfig::class)->baseUrl);
    }

    public function test_install_twice_preserves_config_and_never_prints_secrets(): void
    {
        self::assertSame(0, Artisan::call('neokpay:install'));
        self::assertFileExists(config_path('neokpay.php'));
        $hash = hash_file('sha256', config_path('neokpay.php'));
        self::assertSame(0, Artisan::call('neokpay:install'));
        self::assertSame($hash, hash_file('sha256', config_path('neokpay.php')));
        $output = Artisan::output();
        self::assertStringContainsString('already exists', $output);
        self::assertStringNotContainsString('test-secret', $output);
        self::assertStringNotContainsString('test-key', $output);
    }

    public function test_doctor_reports_runtime_packages_and_good_configuration(): void
    {
        Artisan::call('neokpay:install');
        self::assertSame(0, Artisan::call('neokpay:doctor'));
        $output = Artisan::output();
        foreach (['PHP '.PHP_VERSION, 'Laravel '.app()->version(), 'SDK Package', 'HTTP Client', 'Webhook Route', 'devneok/neokpay-php:'] as $label) {
            self::assertStringContainsString($label, $output);
        }
        self::assertStringNotContainsString('test-key', $output);
        self::assertStringNotContainsString('test-secret', $output);
    }

    public function test_doctor_reports_missing_credentials(): void
    {
        Artisan::call('neokpay:install');
        config(['neokpay.api_key' => null, 'neokpay.webhook_secret' => null]);
        self::assertSame(1, Artisan::call('neokpay:doctor'));
        $output = Artisan::output();
        self::assertMatchesRegularExpression('/API Key\s+MISSING/', $output);
        self::assertMatchesRegularExpression('/Webhook Secret\s+MISSING/', $output);
    }

    #[DataProvider('badUrls')]
    public function test_doctor_rejects_invalid_base_url(string $url): void
    {
        Artisan::call('neokpay:install');
        config(['neokpay.base_url' => $url]);
        self::assertSame(1, Artisan::call('neokpay:doctor'));
        self::assertMatchesRegularExpression('/Base URL\s+MISSING/', Artisan::output());
    }

    public static function badUrls(): array
    {
        return [[''], ['not-a-url'], ['http://example.test'], ['https://user:pass@example.test']];
    }

    private function body(string $type = 'payment.succeeded'): string
    {
        return json_encode([
            'id' => 'evt_1',
            'type' => $type,
            'created_at' => '2026-09-18T00:00:00+00:00',
            'data' => ['payment' => [
                'id' => 'trx_1', 'reference' => 'ORDER-1', 'amount' => '25.00',
                'currency' => 'USD', 'status' => 'paid',
                'created_at' => '2026-09-18T00:00:00+00:00', 'paid_at' => null,
            ]],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    private function signature(string $body, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'test-secret');
    }

    public function test_valid_webhook_reaches_normal_listener_with_payment_data(): void
    {
        $received = null;
        Event::listen(PaymentSucceeded::class, function (PaymentSucceeded $event) use (&$received): void {
            $received = $event->event->payment;
        });
        $body = $this->body();
        $this->call('POST', '/neokpay/webhook', [], [], [], [
            'HTTP_X_NEOKPAY_SIGNATURE' => $this->signature($body),
        ], $body)->assertNoContent();
        self::assertSame('trx_1', $received->id);
        self::assertSame('ORDER-1', $received->reference);
        self::assertSame('25.00', $received->amount);
        self::assertSame('USD', $received->currency);
        self::assertSame('paid', $received->status->value);
    }

    #[DataProvider('rejectedWebhookCases')]
    public function test_invalid_webhooks_never_dispatch_success(string $case): void
    {
        Event::fake([PaymentSucceeded::class, UnknownWebhookReceived::class]);
        $body = $case === 'malformed' ? '{' : $this->body();
        $signature = $this->signature($body, $case === 'stale' ? time() - 301 : time());
        if ($case === 'invalid') {
            $signature = 'invalid';
        }
        if ($case === 'missing-secret') {
            config(['neokpay.webhook_secret' => null]);
        }
        if ($case === 'changed-whitespace') {
            $body = json_encode(json_decode($body, true));
        }
        $this->call('POST', '/neokpay/webhook', [], [], [], [
            'HTTP_X_NEOKPAY_SIGNATURE' => $signature,
        ], $body)->assertBadRequest()->assertJson(['error' => 'Invalid NEOK Pay webhook.']);
        Event::assertNotDispatched(PaymentSucceeded::class);
        Event::assertNotDispatched(UnknownWebhookReceived::class);
    }

    public static function rejectedWebhookCases(): array
    {
        return [['invalid'], ['stale'], ['malformed'], ['missing-secret'], ['changed-whitespace']];
    }

    public function test_unknown_verified_event_is_dispatched(): void
    {
        Event::fake([UnknownWebhookReceived::class, PaymentSucceeded::class]);
        $body = $this->body('future.event');
        $this->call('POST', '/neokpay/webhook', [], [], [], [
            'HTTP_X_NEOKPAY_SIGNATURE' => $this->signature($body),
        ], $body)->assertNoContent();
        Event::assertDispatched(UnknownWebhookReceived::class);
        Event::assertNotDispatched(PaymentSucceeded::class);
    }

    public function test_boot_and_install_need_no_database_or_web_session(): void
    {
        config(['database.default' => 'connection-does-not-exist']);
        self::assertSame(0, Artisan::call('neokpay:install'));
        self::assertInstanceOf(Client::class, app(Client::class));
        $route = app('router')->getRoutes()->getByName('neokpay.webhook');
        self::assertNotNull($route);
        self::assertNotContains('web', $route->gatherMiddleware());
        self::assertContains('api', $route->gatherMiddleware());
    }

    public function test_facade_checkout_uses_sdk_and_configured_transport(): void
    {
        $history = [];
        $payload = json_decode($this->body(), true)['data']['payment'];
        $payload['checkout_url'] = 'https://checkout.test/1';
        $stack = HandlerStack::create(new MockHandler([new Response(201, [], json_encode(['data' => $payload]))]));
        $stack->push(Middleware::history($history));
        app()->instance(ClientInterface::class, new GuzzleClient(['handler' => $stack]));
        config(['neokpay.base_url' => 'https://fake.test/api/v1']);
        $checkout = NeokPay::checkouts()->create(
            new CreateCheckoutRequest('ORDER-1', '25.00', 'USD', 'Order', 'https://m.test/hook',
                'https://m.test/success', 'https://m.test/cancel', 'Ada', 'ada@example.test'),
            'order-1'
        );
        self::assertSame('https://checkout.test/1', $checkout->checkoutUrl);
        self::assertSame('https://fake.test/api/v1/checkouts', (string) $history[0]['request']->getUri());
        self::assertSame('Bearer test-key', $history[0]['request']->getHeaderLine('Authorization'));
        self::assertSame('order-1', $history[0]['request']->getHeaderLine('Idempotency-Key'));
    }
}
