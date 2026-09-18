<?php

declare(strict_types=1);

namespace Neok\Pay\Laravel\Commands;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Neok\Pay\Client;
use Neok\Pay\Laravel\Support\RuntimeCompatibility;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class DoctorCommand extends Command
{
    protected $signature = 'neokpay:doctor';

    protected $description = 'Diagnose NEOK Pay Laravel integration';

    public function handle(): int
    {
        $this->info('NEOK Pay Doctor');
        $major = (int) explode('.', app()->version())[0];
        $url = (string) config('neokpay.base_url');
        $checks = [
            'PHP '.PHP_VERSION => RuntimeCompatibility::supportsPhp(PHP_VERSION),
            'Laravel '.app()->version() => in_array($major, [11, 12, 13], true),
            'SDK Package' => class_exists(Client::class),
            'Config File' => is_file(config_path('neokpay.php')),
            'API Key' => (string) config('neokpay.api_key') !== '',
            'Webhook Secret' => (string) config('neokpay.webhook_secret') !== '',
            'Base URL' => filter_var($url, FILTER_VALIDATE_URL) !== false
                && parse_url($url, PHP_URL_SCHEME) === 'https'
                && parse_url($url, PHP_URL_USER) === null
                && parse_url($url, PHP_URL_QUERY) === null,
            'Webhook Route' => ! config('neokpay.webhook.enabled') || app('router')->has('neokpay.webhook'),
            'HTTP Client' => app()->bound(ClientInterface::class)
                && app()->bound(RequestFactoryInterface::class)
                && app()->bound(StreamFactoryInterface::class),
        ];
        foreach ($checks as $name => $pass) {
            $this->line(str_pad($name, 28).($pass ? 'PASS' : 'MISSING / INVALID'));
        }
        foreach (['neok/neokpay-laravel', 'neok/neokpay-php'] as $package) {
            $version = InstalledVersions::isInstalled($package)
                ? InstalledVersions::getPrettyVersion($package) : null;
            $this->line($package.': '.($version ?? 'development / unavailable'));
        }
        $this->line('API authentication: not tested (no safe health/auth endpoint).');

        return in_array(false, $checks, true) ? 1 : 0;
    }
}
