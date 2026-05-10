<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Extractor;

use OCA\NCExtrak\Exception\ExtractionException;
use Symfony\Component\Process\Process;

class RarExtractor implements ExtractorInterface
{
    public function getFormats(): array
    {
        return ['rar'];
    }

    public function isAvailable(): bool
    {
        return class_exists(\RarArchive::class) || $this->isUnrarAvailable();
    }

    public function extract(string $archivePath, string $destinationPath): void
    {
        $this->ensureDirectory($destinationPath);

        if (class_exists(\RarArchive::class)) {
            $this->extractWithExtension($archivePath, $destinationPath);
            return;
        }

        $this->extractWithBinary($archivePath, $destinationPath);
    }

    private function extractWithExtension(string $archivePath, string $destinationPath): void
    {
        $archive = \RarArchive::open($archivePath);
        if ($archive === false) {
            throw new ExtractionException('Unable to open rar archive');
        }

        $entries = $archive->getEntries();
        if (!is_array($entries)) {
            $archive->close();
            throw new ExtractionException('Unable to read rar archive entries');
        }

        foreach ($entries as $entry) {
            $entryName = str_replace('\\', '/', (string) $entry->getName());
            $normalized = trim($entryName, '/');
            if ($normalized === '') {
                continue;
            }

            SafePath::assertRelative($normalized);
            if (!$entry->isDirectory()) {
                $this->ensureDirectory(dirname($destinationPath . DIRECTORY_SEPARATOR . $normalized));
            }

            if ($entry->extract($destinationPath, '', '', true) !== true) {
                $archive->close();
                throw new ExtractionException('Failed to extract rar entry');
            }
        }

        $archive->close();
    }

    private function extractWithBinary(string $archivePath, string $destinationPath): void
    {
        $listProcess = new Process(['unrar', 'lb', $archivePath]);
        $listProcess->run();
        if (!$listProcess->isSuccessful()) {
            throw new ExtractionException('Unable to list rar archive entries');
        }

        $entries = preg_split('/\r\n|\r|\n/', trim($listProcess->getOutput())) ?: [];
        foreach ($entries as $entry) {
            $normalized = trim(str_replace('\\', '/', $entry), '/');
            if ($normalized === '') {
                continue;
            }
            SafePath::assertRelative($normalized);
        }

        $extractProcess = new Process(['unrar', 'x', '-idq', '-o+', $archivePath, $destinationPath]);
        $extractProcess->run();
        if (!$extractProcess->isSuccessful()) {
            throw new ExtractionException('Unable to extract rar archive');
        }
    }

    private function isUnrarAvailable(): bool
    {
        $process = new Process(['unrar']);
        $process->run();
        return $process->getExitCode() !== 127 && $process->getExitCode() !== 9009;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
            throw new ExtractionException('Unable to create extraction directory');
        }
    }
}
