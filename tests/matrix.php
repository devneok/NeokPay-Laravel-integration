<?php

declare(strict_types=1);

// Generates an isolated development root. Never edits either public manifest.
$root = $argv[1] ?? throw new RuntimeException('Usage: php tests/matrix.php TEMP_ROOT LARAVEL_MAJOR [SDK_PATH|registry]');
$major = (int) ($argv[2] ?? 0);
if (! in_array($major, [11, 12, 13], true) || file_exists($root)) {
    throw new RuntimeException('Choose Laravel 11–13 and a new temporary directory.');
}
$wrapper = dirname(__DIR__);
$registry = ($argv[3] ?? '') === 'registry';
$sdk = $registry ? null : realpath($argv[3] ?? dirname($wrapper).'/neokpay-php');
if (! $registry && $sdk === false) {
    throw new RuntimeException('SDK sibling not found.');
}
mkdir($root, 0700, true);
$manifest = [
    'name' => 'neok/certification-root',
    'description' => 'Isolated unpublished package compatibility tests',
    'license' => 'MIT',
    'require' => [
        'php' => '^8.3',
        'devneok/neokpay-php' => '^1.0.0-beta.2@beta',
        'devneok/neokpay-laravel' => 'dev-main',
        'laravel/framework' => $major === 11 ? '^11.56.1' : '^'.$major.'.0',
        'orchestra/testbench' => '^'.($major - 2).'.0',
        'phpunit/phpunit' => '^11.5',
    ],
    'require-dev' => ['phpstan/phpstan' => '2.2.14', 'larastan/larastan' => '3.12.1'],
    'repositories' => [
        ['type' => 'path', 'url' => $wrapper, 'options' => ['symlink' => true, 'versions' => ['devneok/neokpay-laravel' => 'dev-main']]],
    ],
    'autoload-dev' => ['psr-4' => ['Neok\\Pay\\Laravel\\Tests\\' => $wrapper.'/tests/']],
    'prefer-stable' => true,
];
if ($major === 11) {
    // Compatibility, not security certification: no patched Laravel 11 exists
    // for these advisories. Composer 2.10+ still reports them during audit.
    $ignores = [];
    foreach (require __DIR__.'/laravel11-advisories.php' as $id) {
        $ignores[$id] = [
            'on-audit' => false,
            'reason' => 'Isolated Laravel 11 compatibility only; no patched 11.x available. Audit remains mandatory.',
        ];
    }
    $manifest['config']['policy']['advisories']['ignore-id'] = $ignores;
}
if (! $registry) {
    // Simulated release metadata only in this root; no tag is created.
    array_unshift($manifest['repositories'], ['type' => 'path', 'url' => $sdk, 'options' => [
        'symlink' => true, 'versions' => ['devneok/neokpay-php' => '1.0.0-beta.2'],
    ]]);
}
file_put_contents($root.'/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
$testPath = htmlspecialchars($wrapper.'/tests', ENT_XML1);
file_put_contents($root.'/phpunit.xml', '<phpunit bootstrap="vendor/autoload.php"><testsuites><testsuite name="package"><directory>'.$testPath.'</directory></testsuite></testsuites></phpunit>');
file_put_contents($root.'/phpstan.neon', "includes:\n  - vendor/larastan/larastan/extension.neon\nparameters:\n  level: 6\n  paths:\n    - ".$wrapper."/src\n");
echo $root."\n";
