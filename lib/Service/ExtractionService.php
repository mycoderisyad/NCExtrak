<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Service;

use OCA\NCExtrak\Dto\ExtractOptions;
use OCA\NCExtrak\Dto\ExtractResult;
use OCA\NCExtrak\Exception\ArchiveTooLargeException;
use OCA\NCExtrak\Exception\ExtractionException;
use OCA\NCExtrak\Extractor\SafePath;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\ITempManager;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class ExtractionService
{
    public function __construct(
        private IRootFolder $rootFolder,
        private ArchiveDetector $archiveDetector,
        private ExtractorRegistry $extractorRegistry,
        private ITempManager $tempManager,
    ) {
    }

    public function extractForUser(
        string $userId,
        int $fileId,
        ExtractOptions $options,
        ?callable $progressCallback = null,
    ): ExtractResult {
        $userFolder = $this->rootFolder->getUserFolder($userId);
        $nodes = $userFolder->getById($fileId);
        if ($nodes === []) {
            throw new ExtractionException('Archive file not found');
        }

        $source = $nodes[0];
        if (!$source instanceof File) {
            throw new ExtractionException('Selected node is not a file');
        }

        [$workspaceRoot, $tempArchivePath, $tempExtractPath] = $this->createWorkspacePaths($options);

        try {
            $this->reportProgress($progressCallback, 1);
            $this->assertWorkspaceCapacity($workspaceRoot, $source, $options);
            $this->copyNodeToLocal($source, $tempArchivePath);
            $this->reportProgress($progressCallback, 15);
            $format = $this->archiveDetector->detectFormat($tempArchivePath, $source->getName());
            if ($format === null) {
                throw new ExtractionException('Could not detect archive format');
            }

            $extractor = $this->extractorRegistry->getExtractor($format);
            $extractor->extract($tempArchivePath, $tempExtractPath);
            $this->reportProgress($progressCallback, 50);

            [$targetFolder, $targetFolderName] = $this->createTargetFolder($source, $options->overwrite);
            [$fileCount, $folderCount, $totalBytes] = $this->copyExtractedTree(
                $tempExtractPath,
                $targetFolder,
                $options,
                $progressCallback,
            );
            $this->refreshFolderMetadata($targetFolder);
            $this->reportProgress($progressCallback, 99);

            return new ExtractResult(
                format: $format,
                targetFolder: $targetFolderName,
                fileCount: $fileCount,
                folderCount: $folderCount,
                totalBytes: $totalBytes,
            );
        } finally {
            $this->cleanupLocalPath($workspaceRoot);
        }
    }

    private function copyNodeToLocal(File $source, string $localPath): void
    {
        $input = $source->fopen('r');
        $output = fopen($localPath, 'wb');
        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            throw new ExtractionException('Unable to open archive stream');
        }

        stream_copy_to_stream($input, $output);
        fclose($input);
        fclose($output);
    }

    /**
     * @return array{Folder, string}
     */
    private function createTargetFolder(File $source, bool $overwrite): array
    {
        $parent = $source->getParent();
        if (!$parent instanceof Folder) {
            throw new ExtractionException('Unable to resolve target parent folder');
        }

        $baseName = $this->extractBaseFolderName($source->getName());
        $targetName = $baseName;
        $suffix = 1;
        while ($parent->nodeExists($targetName)) {
            if ($overwrite) {
                $parent->get($targetName)->delete();
                break;
            }
            $targetName = sprintf('%s (%d)', $baseName, $suffix);
            $suffix++;
        }

        return [$parent->newFolder($targetName), $targetName];
    }

    /**
     * @return array{int, int, int}
     */
    private function copyExtractedTree(
        string $localRoot,
        Folder $targetFolder,
        ExtractOptions $options,
        ?callable $progressCallback = null,
    ): array {
        $fileCount = 0;
        $folderCount = 0;
        $totalBytes = 0;
        $entryCount = 0;
        $totalEntries = $this->countEntries($localRoot);
        $lastReported = 50;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($localRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            $absolutePath = $fileInfo->getPathname();
            $relativePath = substr($absolutePath, strlen($localRoot) + 1);
            if ($relativePath === '') {
                continue;
            }

            $relativePath = str_replace('\\', '/', $relativePath);
            SafePath::assertRelative($relativePath);

            $entryCount++;
            if ($entryCount > $options->maxEntries) {
                throw new ArchiveTooLargeException('Archive entry count limit exceeded');
            }

            if ($fileInfo->isDir()) {
                $this->resolveFolder($targetFolder, $relativePath);
                $folderCount++;
                continue;
            }

            $targetFileFolder = $this->resolveFolder($targetFolder, dirname($relativePath));
            $fileName = basename($relativePath);
            if ($targetFileFolder->nodeExists($fileName)) {
                if (!$options->overwrite) {
                    throw new ExtractionException(sprintf('Target file already exists: %s', $relativePath));
                }
                $targetFileFolder->get($fileName)->delete();
            }

            $targetFile = $targetFileFolder->newFile($fileName);
            $input = fopen($absolutePath, 'rb');
            $output = $targetFile->fopen('w');
            if ($input === false || $output === false) {
                if (is_resource($input)) {
                    fclose($input);
                }
                if (is_resource($output)) {
                    fclose($output);
                }
                throw new ExtractionException(sprintf('Unable to copy extracted file: %s', $relativePath));
            }

            stream_copy_to_stream($input, $output);
            fclose($input);
            fclose($output);

            $fileCount++;
            $size = $fileInfo->getSize();
            $totalBytes += $size > 0 ? $size : 0;
            if ($totalBytes > $options->maxUncompressedSizeBytes) {
                throw new ArchiveTooLargeException('Uncompressed archive size limit exceeded');
            }

            if ($progressCallback !== null && $totalEntries > 0) {
                $copied = $fileCount + $folderCount;
                $percent = 50 + (int) floor(($copied / $totalEntries) * 49);
                if ($percent > $lastReported) {
                    $this->reportProgress($progressCallback, $percent);
                    $lastReported = $percent;
                }
            }
        }

        return [$fileCount, $folderCount, $totalBytes];
    }

    private function refreshFolderMetadata(Folder $targetFolder): void
    {
        try {
            $storage = $targetFolder->getStorage();
            $scanner = $storage->getScanner();
            $internalPath = $targetFolder->getInternalPath();
            if (is_string($internalPath) && $internalPath !== '') {
                $scanner->scan($internalPath);
            }
            $targetFolder->touch();
        } catch (Throwable) {
            // Keep extraction successful even if metadata refresh fails.
        }
    }

    private function countEntries(string $localRoot): int
    {
        $count = 0;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($localRoot, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($iterator as $entry) {
                $count++;
            }
        } catch (Throwable) {
            return 0;
        }

        return $count;
    }

    private function reportProgress(?callable $progressCallback, int $percent): void
    {
        if ($progressCallback === null) {
            return;
        }

        $clamped = max(0, min(99, $percent));
        try {
            $progressCallback($clamped);
        } catch (Throwable) {
        }
    }

    private function resolveFolder(Folder $rootFolder, string $relativePath): Folder
    {
        $normalized = trim(str_replace('\\', '/', $relativePath), '/');
        if ($normalized === '' || $normalized === '.') {
            return $rootFolder;
        }

        $current = $rootFolder;
        $parts = explode('/', $normalized);
        foreach ($parts as $part) {
            SafePath::assertRelative($part);
            if ($current->nodeExists($part)) {
                $node = $current->get($part);
                if (!$node instanceof Folder) {
                    throw new ExtractionException(sprintf('Path conflict during extraction: %s', $normalized));
                }
                $current = $node;
                continue;
            }

            $current = $current->newFolder($part);
        }

        return $current;
    }

    private function extractBaseFolderName(string $fileName): string
    {
        $normalized = strtolower($fileName);
        $candidates = ['.tar.gz', '.tar.bz2', '.tgz', '.tbz2', '.zip', '.rar', '.7z', '.tar', '.gz', '.bz2'];
        foreach ($candidates as $extension) {
            if (str_ends_with($normalized, $extension)) {
                $base = substr($fileName, 0, -strlen($extension));
                $trimmed = trim((string) $base);
                return $trimmed !== '' ? $trimmed : 'extracted';
            }
        }

        $pathInfo = pathinfo($fileName);
        $filename = (string) $pathInfo['filename'];
        return $filename !== '' ? $filename : 'extracted';
    }

    private function cleanupLocalPath(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();
            if ($item->isDir()) {
                @rmdir($itemPath);
                continue;
            }
            @unlink($itemPath);
        }

        @rmdir($path);
    }

    /**
     * @return array{string, string, string}
     */
    private function createWorkspacePaths(ExtractOptions $options): array
    {
        if ($options->workspaceDirectory !== null) {
            $base = rtrim($options->workspaceDirectory, DIRECTORY_SEPARATOR);
            if ($base === '') {
                throw new ExtractionException('Configured workspace directory is invalid');
            }

            if (!is_dir($base) && !mkdir($base, 0770, true) && !is_dir($base)) {
                throw new ExtractionException('Unable to create configured workspace directory');
            }

            $workspaceRoot = $base . DIRECTORY_SEPARATOR . 'ncextrak_' . bin2hex(random_bytes(12));
            if (!mkdir($workspaceRoot, 0770, true) && !is_dir($workspaceRoot)) {
                throw new ExtractionException('Unable to create extraction workspace');
            }
        } else {
            $workspaceRoot = $this->tempManager->getTemporaryFolder('ncextrak_workspace_');
        }

        $tempArchivePath = $workspaceRoot . DIRECTORY_SEPARATOR . 'archive.bin';
        $tempExtractPath = $workspaceRoot . DIRECTORY_SEPARATOR . 'extracted';
        if (!mkdir($tempExtractPath, 0770, true) && !is_dir($tempExtractPath)) {
            throw new ExtractionException('Unable to create local extraction directory');
        }

        return [$workspaceRoot, $tempArchivePath, $tempExtractPath];
    }

    private function assertWorkspaceCapacity(string $workspaceRoot, File $source, ExtractOptions $options): void
    {
        $sourceSize = (int) $source->getSize();
        if ($sourceSize <= 0) {
            return;
        }

        $workspaceFree = @disk_free_space($workspaceRoot);
        if (!is_float($workspaceFree) && !is_int($workspaceFree)) {
            return;
        }

        $expansionEstimate = min(
            $options->maxUncompressedSizeBytes,
            $sourceSize * max(1, $options->expectedExpansionFactor),
        );
        $requiredBytes = $sourceSize + $expansionEstimate + max(0, $options->workspaceReserveBytes);

        if ($workspaceFree < $requiredBytes) {
            throw new ExtractionException(sprintf(
                'Insufficient workspace free space. Required ~%s, available %s. Configure ncextrak.work_dir to a larger disk.',
                $this->formatBytes($requiredBytes),
                $this->formatBytes((int) $workspaceFree),
            ));
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $index = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return sprintf('%.2f %s', $value, $units[$index]);
    }
}
