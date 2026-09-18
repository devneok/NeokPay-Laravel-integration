<?php

declare(strict_types=1);

namespace Neok\Pay\Laravel\Tests;

use Neok\Pay\Laravel\NeokPayServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [NeokPayServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('neokpay.api_key', 'test-key');
        $app['config']->set('neokpay.webhook_secret', 'test-secret');
    }
}
