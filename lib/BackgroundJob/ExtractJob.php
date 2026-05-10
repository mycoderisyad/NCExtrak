<?php

declare(strict_types=1);

namespace OCA\NCExtrak\BackgroundJob;

use OCA\NCExtrak\Db\JobMapper;
use OCA\NCExtrak\Dto\ExtractOptions;
use OCA\NCExtrak\Notification\NotificationService;
use OCA\NCExtrak\Service\ExtractionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\BackgroundJob\QueuedJob;
use OCP\IConfig;
use Throwable;

class ExtractJob extends QueuedJob
{
    public function __construct(
        private JobMapper $jobMapper,
        private ExtractionService $extractionService,
        private NotificationService $notificationService,
        private IConfig $config,
    ) {
    }

    /**
     * @param array{jobId?:int|string}|null $argument
     */
    protected function run($argument): void
    {
        if (!is_array($argument) || !isset($argument['jobId'])) {
            return;
        }

        $jobId = (int) $argument['jobId'];

        try {
            $job = $this->jobMapper->findById($jobId);
        } catch (DoesNotExistException) {
            return;
        }

        $this->jobMapper->markRunning($job);

        try {
            $workDir = trim((string) $this->config->getSystemValue('ncextrak.work_dir', ''));
            $workspaceReserve = (int) $this->config->getSystemValue('ncextrak.work_reserve', 2147483648);
            $expansionFactor = (int) $this->config->getSystemValue('ncextrak.expected_expansion_factor', 2);

            $options = new ExtractOptions(
                syncSizeLimitBytes: (int) $this->config->getSystemValue('ncextrak.sync_size_limit', 52428800),
                maxEntries: (int) $this->config->getSystemValue('ncextrak.max_entries', 100000),
                maxUncompressedSizeBytes: (int) $this->config->getSystemValue('ncextrak.max_size', 2199023255552),
                workspaceDirectory: $workDir !== '' ? $workDir : null,
                workspaceReserveBytes: $workspaceReserve,
                expectedExpansionFactor: max(1, $expansionFactor),
                overwrite: $job->getOverwrite(),
            );

            $result = $this->extractionService->extractForUser(
                $job->getUid(),
                $job->getSourceFileId(),
                $options,
            );

            $this->jobMapper->markDone($job, (string) json_encode([
                'format' => $result->format,
                'targetFolder' => $result->targetFolder,
                'fileCount' => $result->fileCount,
                'folderCount' => $result->folderCount,
                'totalBytes' => $result->totalBytes,
            ], JSON_THROW_ON_ERROR));

            $this->notificationService->notifySuccess($job->getUid(), $jobId, $result->targetFolder);
        } catch (Throwable $throwable) {
            $this->jobMapper->markFailed($job, $throwable->getMessage());
            $this->notificationService->notifyFailure($job->getUid(), $jobId, $throwable->getMessage());
        }
    }
}
