<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Service;

use OCA\NCExtrak\Dto\ExtractOptions;
use OCP\IConfig;

class ExtractOptionsFactory
{
    private const PROFILE_HOME_SERVER = 'home_server';
    private const PROFILE_BALANCED = 'balanced';
    private const PROFILE_HIGH_THROUGHPUT = 'high_throughput';

    public function __construct(private IConfig $config)
    {
    }

    public function create(bool $overwrite): ExtractOptions
    {
        $profile = strtolower(trim((string) $this->config->getSystemValue('ncextrak.profile', self::PROFILE_HOME_SERVER)));
        $defaults = $this->resolveDefaults($profile);
        $dataDirectory = trim((string) $this->config->getSystemValue('datadirectory', ''));
        $profileWorkDir = $dataDirectory !== ''
            ? rtrim($dataDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ncextrak-work'
            : '';

        $syncLimit = (int) $this->config->getSystemValue('ncextrak.sync_size_limit', $defaults['syncSizeLimitBytes']);
        $maxEntries = (int) $this->config->getSystemValue('ncextrak.max_entries', $defaults['maxEntries']);
        $maxSize = (int) $this->config->getSystemValue('ncextrak.max_size', $defaults['maxUncompressedSizeBytes']);
        $workDir = trim((string) $this->config->getSystemValue('ncextrak.work_dir', $profileWorkDir));
        $workspaceReserve = (int) $this->config->getSystemValue('ncextrak.work_reserve', $defaults['workspaceReserveBytes']);
        $expansionFactor = (int) $this->config->getSystemValue('ncextrak.expected_expansion_factor', $defaults['expectedExpansionFactor']);

        return new ExtractOptions(
            syncSizeLimitBytes: max(1, $syncLimit),
            maxEntries: max(1, $maxEntries),
            maxUncompressedSizeBytes: max(1, $maxSize),
            workspaceDirectory: $workDir !== '' ? $workDir : null,
            workspaceReserveBytes: max(0, $workspaceReserve),
            expectedExpansionFactor: max(1, $expansionFactor),
            overwrite: $overwrite,
        );
    }

    /**
     * @return array{
     *   syncSizeLimitBytes:int,
     *   maxEntries:int,
     *   maxUncompressedSizeBytes:int,
     *   workspaceReserveBytes:int,
     *   expectedExpansionFactor:int
     * }
     */
    private function resolveDefaults(string $profile): array
    {
        return match ($profile) {
            self::PROFILE_HIGH_THROUGHPUT => [
                'syncSizeLimitBytes' => 33554432,
                'maxEntries' => 1000000,
                'maxUncompressedSizeBytes' => 4398046511104,
                'workspaceReserveBytes' => 17179869184,
                'expectedExpansionFactor' => 2,
            ],
            self::PROFILE_BALANCED => [
                'syncSizeLimitBytes' => 16777216,
                'maxEntries' => 600000,
                'maxUncompressedSizeBytes' => 3298534883328,
                'workspaceReserveBytes' => 12884901888,
                'expectedExpansionFactor' => 2,
            ],
            default => [
                'syncSizeLimitBytes' => 8388608,
                'maxEntries' => 400000,
                'maxUncompressedSizeBytes' => 2199023255552,
                'workspaceReserveBytes' => 8589934592,
                'expectedExpansionFactor' => 2,
            ],
        };
    }
}
