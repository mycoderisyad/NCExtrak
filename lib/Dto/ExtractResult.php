<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Dto;

final readonly class ExtractResult
{
    public function __construct(
        public string $format,
        public string $targetFolder,
        public int $fileCount,
        public int $folderCount,
        public int $totalBytes,
    ) {
    }
}
