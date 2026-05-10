<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Extractor;

use OCA\NCExtrak\Exception\ExtractionException;
use ZipArchive;

class ZipExtractor implements ExtractorInterface
{
    public function getFormats(): array
    {
        return ['zip'];
    }

    public function isAvailable(): bool
    {
        return class_exists(ZipArchive::class);
    }

    public function extract(string $archivePath, string $destinationPath): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($archivePath);
        if ($result !== true) {
            throw new ExtractionException('Unable to open zip archive');
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $rawEntryName = $zip->getNameIndex($index);
            if ($rawEntryName === false) {
                continue;
            }

            $entryName = str_replace('\\', '/', $rawEntryName);
            $isDirectory = str_ends_with($entryName, '/');
            $entryPath = trim($entryName, '/');
            if ($entryPath === '') {
                continue;
            }

            SafePath::assertRelative($entryPath);
            $targetPath = $destinationPath . DIRECTORY_SEPARATOR . $entryPath;

            if ($isDirectory) {
                $this->ensureDirectory($targetPath);
                continue;
            }

            $this->ensureDirectory(dirname($targetPath));
            $input = $zip->getStream($rawEntryName);
            if ($input === false) {
                throw new ExtractionException('Unable to read zip entry stream');
            }

            $output = fopen($targetPath, 'wb');
            if ($output === false) {
                fclose($input);
                throw new ExtractionException('Unable to write extracted file');
            }

            stream_copy_to_stream($input, $output);
            fclose($input);
            fclose($output);
        }

        $zip->close();
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
            throw new ExtractionException('Unable to create extraction directory');
        }
    }
}
