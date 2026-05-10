<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Extractor;

use OCA\NCExtrak\Exception\ExtractionException;
use Symfony\Component\Process\Process;

class SevenZipExtractor implements ExtractorInterface
{
    private const BINARIES = ['7z', '7za'];

    public function getFormats(): array
    {
        return ['7z'];
    }

    public function isAvailable(): bool
    {
        return $this->resolveBinary() !== null;
    }

    public function extract(string $archivePath, string $destinationPath): void
    {
        $binary = $this->resolveBinary();
        if ($binary === null) {
            throw new ExtractionException('7z binary is not available');
        }

        $this->ensureDirectory($destinationPath);
        $this->validateEntries($binary, $archivePath);

        $process = new Process([$binary, 'x', '-y', '-bso0', '-bsp0', '-o' . $destinationPath, $archivePath]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ExtractionException('Unable to extract 7z archive');
        }
    }

    private function validateEntries(string $binary, string $archivePath): void
    {
        $process = new Process([$binary, 'l', '-ba', '-slt', $archivePath]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ExtractionException('Unable to inspect 7z archive entries');
        }

        $lines = preg_split('/\r\n|\r|\n/', $process->getOutput()) ?: [];
        foreach ($lines as $line) {
            if (!str_starts_with($line, 'Path = ')) {
                continue;
            }

            $entryPath = trim(substr($line, 7));
            if ($entryPath === '' || $entryPath === '.') {
                continue;
            }
            SafePath::assertRelative($entryPath);
        }
    }

    private function resolveBinary(): ?string
    {
        foreach (self::BINARIES as $binary) {
            $process = new Process([$binary]);
            $process->run();
            $exitCode = $process->getExitCode();
            if ($exitCode !== 127 && $exitCode !== 9009) {
                return $binary;
            }
        }

        return null;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0770, true) && !is_dir($path)) {
            throw new ExtractionException('Unable to create extraction directory');
        }
    }
}
