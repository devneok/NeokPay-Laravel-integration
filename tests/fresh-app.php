<?php

declare(strict_types=1);

// Run against an isolated, installed application, never the payment server.
use Composer\InstalledVersions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Neok\Pay\Client;
use Neok\Pay\Laravel\Events\PaymentSucceeded;
use Neok\Pay\Laravel\Events\UnknownWebhookReceived;
use Neok\Pay\Laravel\Facades\NeokPay;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

$root = $argv[1];
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing')) {
    throw new RuntimeException('Use a disposable application with APP_ENV=testing.');
}
config(['neokpay.api_key' => 'fake-certification-key']);
$checks = 0;
$check = function (bool $condition, string $label) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException('FAIL: '.$label);
    }
    $checks++;
    echo 'PASS: '.$label."\n";
};
$check(app(Client::class) === NeokPay::getFacadeRoot(), 'facade and DI identity');
foreach ([ClientInterface::class, RequestFactoryInterface::class, StreamFactoryInterface::class] as $interface) {
    $check(app($interface) instanceof $interface, $interface);
}
$check(app('router')->has('neokpay.webhook'), 'auto-discovered webhook route');
$check(Artisan::call('neokpay:install') === 0, 'installer first run');
$hash = hash_file('sha256', config_path('neokpay.php'));
$check(Artisan::call('neokpay:install') === 0, 'installer second run');
$check($hash === hash_file('sha256', config_path('neokpay.php')), 'configuration unchanged');
config(['neokpay.api_key' => null, 'neokpay.webhook_secret' => null]);
$check(Artisan::call('neokpay:doctor') === 1, 'doctor missing credentials');
config(['neokpay.api_key' => 'fake-certification-key', 'neokpay.webhook_secret' => 'fake-certification-secret']);
$check(Artisan::call('neokpay:doctor') === 0, 'doctor configured');
$output = Artisan::output();
$check(! str_contains($output, 'fake-certification-'), 'doctor redacts credentials');
foreach (['devneok/neokpay-php', 'devneok/neokpay-laravel'] as $package) {
    $version = InstalledVersions::getPrettyVersion($package);
    $check(is_string($version) && str_contains($output, $package.': '.$version), 'doctor reports installed '.$package.' version');
}
config(['neokpay.base_url' => 'not-a-url']);
$check(Artisan::call('neokpay:doctor') === 1, 'doctor invalid URL');
config(['neokpay.base_url' => 'https://pay.neok.me/api/v1']);
$received = [];
Event::listen(PaymentSucceeded::class, function (PaymentSucceeded $event) use (&$received): void {
    $payment = $event->event->payment;
    $received[] = [$payment->id, $payment->reference, $payment->amount, $payment->currency, $payment->status->value];
});
$unknown = 0;
Event::listen(UnknownWebhookReceived::class, function () use (&$unknown): void {
    $unknown++;
});
$payload = [
    'id' => 'evt_fake', 'type' => 'payment.succeeded', 'created_at' => '2026-09-18T00:00:00Z',
    'data' => ['payment' => ['id' => 'payment_fake', 'reference' => 'ORDER-1', 'amount' => '25.00',
        'currency' => 'USD', 'status' => 'paid', 'created_at' => '2026-09-18T00:00:00Z', 'paid_at' => null]],
];
$body = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
$kernel = app(Illuminate\Contracts\Http\Kernel::class);
foreach (['valid', 'invalid', 'stale', 'malformed', 'changed-whitespace', 'unknown'] as $case) {
    $raw = $body;
    if ($case === 'malformed') {
        $raw = '{';
    }
    if ($case === 'unknown') {
        $payload['type'] = 'future.event';
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    }
    $timestamp = time() - ($case === 'stale' ? 301 : 0);
    $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$raw, 'fake-certification-secret');
    if ($case === 'invalid') {
        $signature = 'invalid';
    }
    if ($case === 'changed-whitespace') {
        $raw .= "\n";
    }
    $request = Request::create('/neokpay/webhook', 'POST', [], [], [], [
        'HTTP_X_NEOKPAY_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json',
    ], $raw);
    $response = $kernel->handle($request);
    $check($response->getStatusCode() === (in_array($case, ['valid', 'unknown'], true) ? 204 : 400), 'webhook '.$case);
    $kernel->terminate($request, $response);
}
$check($received === [['payment_fake', 'ORDER-1', '25.00', 'USD', 'paid']], 'normal listener data; invalid requests did not dispatch');
$check($unknown === 1, 'unknown event listener');
Route::post('/certification-csrf-probe', fn () => response('', 204))->middleware('web');
$app['env'] = 'production';
$csrfResponse = $kernel->handle(Request::create('/certification-csrf-probe', 'POST'));
$check($csrfResponse->getStatusCode() === 419, 'ordinary web POST still requires CSRF');
$check(! file_exists($root.'/database/database.sqlite'), 'no database created or migrated');
echo 'Laravel '.app()->version().'; PHP '.PHP_VERSION.'; '.$checks." checks PASS\n";
