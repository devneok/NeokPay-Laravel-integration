<?php

declare(strict_types=1);

namespace Neok\Pay\Laravel\Support;

final class RuntimeCompatibility
{
    public static function supportsPhp(string $version): bool
    {
        return version_compare($version, '8.3', '>=') && version_compare($version, '9.0', '<');
    }
}
