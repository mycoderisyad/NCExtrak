<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Dto;

final readonly class ExtractOptions
{
    public function __construct(
        public int $syncSizeLimitBytes = 52428800,
        public int $maxEntries = 100000,
        public int $maxUncompressedSizeBytes = 2199023255552,
        public ?string $workspaceDirectory = null,
        public int $workspaceReserveBytes = 2147483648,
        public int $expectedExpansionFactor = 2,
        public bool $overwrite = false,
    ) {
    }
}
