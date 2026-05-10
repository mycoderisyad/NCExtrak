<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Extractor;

use OCA\NCExtrak\Exception\ExtractionException;
use PharData;
use RecursiveIteratorIterator;

class TarExtractor implements ExtractorInterface
{
    public function getFormats(): array
    {
        return ['tar'];
    }

    public function isAvailable(): bool
    {
        return class_exists(PharData::class);
    }

    public function extract(string $archivePath, string $destinationPath): void
    {
        try {
            $phar = new PharData($archivePath);
        } catch (\Throwable $exception) {
            throw new ExtractionException('Unable to open tar archive', 0, $exception);
        }

        $iterator = new RecursiveIteratorIterator($phar, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $fileInfo) {
            $entryName = str_replace('\\', '/', $iterator->getSubPathName());
            if ($entryName === '') {
                continue;
            }

            SafePath::assertRelative($entryName);
            $targetPath = $destinationPath . DIRECTORY_SEPARATOR . $entryName;

            if ($fileInfo->isDir()) {
                $this->ensureDirectory($targetPath);
                continue;
            }

            $this->ensureDirectory(dirname($targetPath));
            $sourceStream = fopen($fileInfo->getPathname(), 'rb');
            $targetStream = fopen($targetPath, 'wb');
            if ($sourceStream === false || $targetStream === false) {
                if (is_resource($sourceStream)) {
                    fclose($sourceStream);
                }
                if (is_resource($targetStream)) {
                    fclose($targetStream);
                }
                throw new ExtractionException('Unable to stream tar entry');
            }

            stream_copy_to_stream($sourceStream, $targetStream);
            fclose($sourceStream);
            fclose($targetStream);
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
            throw new ExtractionException('Unable to create extraction directory');
        }
    }
}
