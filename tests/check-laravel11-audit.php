<?php

declare(strict_types=1);

// Consume Composer's unfiltered audit output. Only the explicitly reviewed
// Laravel 11 findings are tolerated; malformed output/new findings fail CI.
$path = $argv[1] ?? throw new RuntimeException('Supply composer audit JSON and exit code.');
$exitCode = (int) ($argv[2] ?? -1);
$report = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$allowed = require __DIR__.'/laravel11-advisories.php';
if (! is_array($report) || ! isset($report['advisories']) || ! is_array($report['advisories']) || ! in_array($exitCode, [0, 1], true)) {
    throw new RuntimeException('Composer audit did not complete successfully.');
}
$unexpected = false;
$summary = "### Laravel 11 security audit\n\nCompatibility testing only: this framework is **not security-clean**. Use a current patched framework for production.\n\n";
foreach ($report['advisories'] as $package => $advisories) {
    foreach ($advisories as $advisory) {
        $id = $advisory['advisoryId'] ?? 'unknown';
        $title = $advisory['title'] ?? 'Unknown advisory';
        $expected = $package === 'laravel/framework' && in_array($id, $allowed, true);
        $summary .= '- '.$package.' '.$id.': '.$title.($expected ? ' (test-only exception)' : ' (UNEXPECTED)')."\n";
        $unexpected = $unexpected || ! $expected;
    }
}
// Composer may add new policy categories. Fail closed on non-empty findings.
foreach ($report as $category => $findings) {
    if ($category !== 'advisories' && ! empty($findings)) {
        $summary .= '- Unexpected audit category: '.$category."\n";
        $unexpected = true;
    }
}
echo $summary;
$summaryPath = getenv('GITHUB_STEP_SUMMARY');
if ($summaryPath !== false && $summaryPath !== '') {
    file_put_contents($summaryPath, $summary, FILE_APPEND);
}
exit($unexpected ? 1 : 0);
