<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Service;

class ArchiveDetector
{
    public function detectFormat(string $filePath, string $fileName): ?string
    {
        $header = $this->readHeader($filePath);
        $lowerName = strtolower($fileName);

        if (str_starts_with($header, "PK\x03\x04")) {
            return 'zip';
        }
        if (str_starts_with($header, "Rar!\x1A\x07")) {
            return 'rar';
        }
        if (str_starts_with($header, "7z\xBC\xAF\x27\x1C")) {
            return '7z';
        }
        if (str_starts_with($header, "\x1F\x8B") || str_starts_with($header, 'BZh')) {
            return 'tar';
        }
        if (strlen($header) >= 262 && substr($header, 257, 5) === 'ustar') {
            return 'tar';
        }

        return match (true) {
            str_ends_with($lowerName, '.zip') => 'zip',
            str_ends_with($lowerName, '.rar') => 'rar',
            str_ends_with($lowerName, '.7z') => '7z',
            str_ends_with($lowerName, '.tar'),
            str_ends_with($lowerName, '.tgz'),
            str_ends_with($lowerName, '.tbz2'),
            str_ends_with($lowerName, '.tar.gz'),
            str_ends_with($lowerName, '.tar.bz2'),
            str_ends_with($lowerName, '.gz'),
            str_ends_with($lowerName, '.bz2') => 'tar',
            default => null,
        };
    }

    private function readHeader(string $filePath): string
    {
        $stream = fopen($filePath, 'rb');
        if ($stream === false) {
            return '';
        }

        $header = fread($stream, 560);
        fclose($stream);

        return $header !== false ? $header : '';
    }
}
