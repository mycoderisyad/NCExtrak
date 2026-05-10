<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Extractor;

use OCA\NCExtrak\Exception\ExtractionException;

final class SafePath
{
    public static function assertRelative(string $entryName): void
    {
        $normalized = str_replace('\\', '/', trim($entryName));

        if ($normalized === '' || str_starts_with($normalized, '/')) {
            throw new ExtractionException('Invalid archive entry path');
        }

        $segments = explode('/', $normalized);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new ExtractionException('Unsafe archive entry path');
            }
        }
    }
}
