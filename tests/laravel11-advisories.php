<?php

declare(strict_types=1);

// Reviewed 2026-09-18 against Laravel 11.56.1 (latest 11.x).
// TEST ROOT ONLY. Never merge this policy into published package metadata.
// Remove an exception when a patched 11.x release becomes available.
return [
    'PKSA-m5cs-t1y6-qpcs', // Temporary signed URL path confusion.
    'PKSA-3r5d-mb8f-1qw9', // Email validation CRLF injection.
    'PKSA-mdq4-51ck-6kdq', // Same CRLF issue, second advisory source.
];
