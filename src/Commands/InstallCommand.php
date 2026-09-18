<?php

declare(strict_types=1);

namespace Neok\Pay\Laravel\Commands;

use Illuminate\Console\Command;
use Neok\Pay\Client;
use Neok\Pay\Laravel\Support\RuntimeCompatibility;

final class InstallCommand extends Command
{
    protected $signature = 'neokpay:install';

    protected $description = 'Publish NEOK Pay configuration and show setup steps';

    public function handle(): int
    {
        $this->info('NEOK Pay Installer');
        $major = (int) explode('.', app()->version())[0];
        if (! RuntimeCompatibility::supportsPhp(PHP_VERSION)
            || ! in_array($major, [11, 12, 13], true) || ! class_exists(Client::class)) {
            $this->error('Requires PHP 8.3+, Laravel 11–13 and neok/neokpay-php.');

            return 1;
        }
        $this->line('PHP '.PHP_VERSION.' PASS; Laravel '.app()->version().' PASS');
        if (is_file(config_path('neokpay.php'))) {
            $this->line('Configuration already exists; preserved.');
        } else {
            $this->call('vendor:publish', ['--tag' => 'neokpay-config', '--force' => false]);
        }
        $this->line('Add these values to .env (this command does not edit .env):');
        $this->line('NEOKPAY_API_KEY=');
        $this->line('NEOKPAY_WEBHOOK_SECRET=');
        $this->line('NEOKPAY_BASE_URL=https://pay.neok.me/api/v1');
        $this->line('Webhook URL: '.url((string) config('neokpay.webhook.path')));
        $this->line('Next: php artisan neokpay:doctor');

        return 0;
    }
}
