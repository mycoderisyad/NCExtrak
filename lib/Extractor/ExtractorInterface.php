<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Extractor;

interface ExtractorInterface
{
    /**
     * @return list<string>
     */
    public function getFormats(): array;

    public function isAvailable(): bool;

    public function extract(string $archivePath, string $destinationPath): void;
}
