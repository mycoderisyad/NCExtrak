<?php

declare(strict_types=1);

namespace OCA\NCExtrak\BackgroundJob;

use OCA\NCExtrak\Db\JobMapper;
use OCA\NCExtrak\Notification\NotificationService;
use OCA\NCExtrak\Service\ExtractOptionsFactory;
use OCA\NCExtrak\Service\ExtractionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Throwable;

class ExtractJob extends QueuedJob
{
    public function __construct(
        ITimeFactory $time,
        private JobMapper $jobMapper,
        private ExtractionService $extractionService,
        private NotificationService $notificationService,
        private ExtractOptionsFactory $optionsFactory,
    ) {
        parent::__construct($time);
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
            $options = $this->optionsFactory->create($job->getOverwrite());

            $jobMapper = $this->jobMapper;
            $progressCallback = static function (int $percent) use ($jobMapper, $job): void {
                $jobMapper->updateProgress($job, $percent);
            };

            $result = $this->extractionService->extractForUser(
                $job->getUid(),
                $job->getSourceFileId(),
                $options,
                $progressCallback,
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
