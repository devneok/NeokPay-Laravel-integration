<?php

declare(strict_types=1);

// Release validation only: never alters consumer manifests or package runtime.
$root = $argv[1] ?? getcwd();
require $root.'/vendor/autoload.php';
if (Composer\InstalledVersions::getPrettyVersion('devneok/neokpay-php') !== '1.0.0-beta.2'
    || is_link($root.'/vendor/devneok/neokpay-php')) {
    throw new RuntimeException('Installed SDK must be the registry beta.2 distribution, not a symlink.');
}
$lock = json_decode(file_get_contents($root.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$manifest = json_decode(file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($manifest['repositories'] ?? [] as $repository) {
    if (($repository['type'] ?? null) === 'path') {
        $url = $repository['url'] ?? '';
        $path = str_starts_with($url, '/') ? $url : $root.'/'.$url;
        $package = json_decode(file_get_contents($path.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        if (($package['name'] ?? '') !== 'devneok/neokpay-laravel') {
            throw new RuntimeException('Only the unpublished Laravel wrapper may use a path repository.');
        }
    } else {
        throw new RuntimeException('Release validation requires default Packagist, not custom SDK repositories.');
    }
}
foreach ($lock['packages'] as $package) {
    if ($package['name'] !== 'devneok/neokpay-php') {
        continue;
    }
    if ($package['version'] !== '1.0.0-beta.2'
        || ($package['source']['url'] ?? '') !== 'https://github.com/devneok/NeokPay-PHP-SDK.git'
        || ($package['source']['reference'] ?? '') !== '78e19b2c11c610f5fee688956b008140a9e9b2e6'
        || ($package['dist']['type'] ?? '') !== 'zip'
        || ! str_starts_with($package['dist']['url'] ?? '', 'https://api.github.com/repos/devneok/NeokPay-PHP-SDK/zipball/')) {
        throw new RuntimeException('SDK version/source does not match the published beta.2 release.');
    }
    $message = "Published SDK verified: devneok/neokpay-php 1.0.0-beta.2\n"
        ."Source: ".$package['source']['url']."\nCommit: ".$package['source']['reference']."\n"
        ."Registry distribution: ".$package['dist']['url']."\nNo SDK path repository or alias.\n";
    echo $message;
    if ($summary = getenv('GITHUB_STEP_SUMMARY')) {
        file_put_contents($summary, "### SDK registry provenance\n\n```text\n".$message."```\n", FILE_APPEND);
    }
    exit(0);
}
throw new RuntimeException('SDK missing from installed dependency lock.');
