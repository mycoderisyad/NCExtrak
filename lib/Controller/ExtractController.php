<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Controller;

use OCA\NCExtrak\BackgroundJob\ExtractJob;
use OCA\NCExtrak\Db\JobMapper;
use OCA\NCExtrak\Exception\ExtractionException;
use OCA\NCExtrak\Service\ExtractOptionsFactory;
use OCA\NCExtrak\Service\ExtractionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\BackgroundJob\IJobList;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserSession;

class ExtractController extends OCSController
{
    public function __construct(
        string $appName,
        IRequest $request,
        private IUserSession $userSession,
        private IRootFolder $rootFolder,
        private IJobList $jobList,
        private JobMapper $jobMapper,
        private ExtractOptionsFactory $optionsFactory,
        private ExtractionService $extractionService,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function extract(int $fileId, bool $overwrite = false): DataResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new DataResponse(['message' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        $options = $this->optionsFactory->create($overwrite);
        $source = $this->resolveSourceFile($user->getUID(), $fileId);
        if ($source === null) {
            return new DataResponse(['message' => 'File not found'], Http::STATUS_NOT_FOUND);
        }

        $parent = $source->getParent();
        if (($parent->getPermissions() & Constants::PERMISSION_CREATE) === 0) {
            return new DataResponse(['message' => 'Target folder is not writable'], Http::STATUS_FORBIDDEN);
        }

        $fileSize = (int) $source->getSize();
        if ($fileSize > $options->syncSizeLimitBytes) {
            $job = $this->jobMapper->createQueued($user->getUID(), $fileId, $overwrite);
            $this->jobList->add(ExtractJob::class, ['jobId' => $job->getId()]);

            return new DataResponse([
                'mode' => 'async',
                'status' => 'queued',
                'jobId' => $job->getId(),
            ], Http::STATUS_ACCEPTED);
        }

        try {
            $result = $this->extractionService->extractForUser($user->getUID(), $fileId, $options);
        } catch (ExtractionException $exception) {
            return new DataResponse(['message' => $exception->getMessage()], Http::STATUS_BAD_REQUEST);
        }

        return new DataResponse([
            'mode' => 'sync',
            'status' => 'done',
            'result' => [
                'format' => $result->format,
                'targetFolder' => $result->targetFolder,
                'fileCount' => $result->fileCount,
                'folderCount' => $result->folderCount,
                'totalBytes' => $result->totalBytes,
            ],
        ]);
    }

    #[NoAdminRequired]
    public function status(int $jobId): DataResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new DataResponse(['message' => 'Authentication required'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $job = $this->jobMapper->findByIdForUser($jobId, $user->getUID());
        } catch (DoesNotExistException) {
            return new DataResponse(['message' => 'Job not found'], Http::STATUS_NOT_FOUND);
        }

        $payload = null;
        $resultPayload = $job->getResultPayload();
        if ($resultPayload !== null && $resultPayload !== '') {
            $decoded = json_decode($resultPayload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return new DataResponse([
            'id' => $job->getId(),
            'state' => $job->getState(),
            'progress' => $job->getProgress(),
            'error' => $job->getError(),
            'result' => $payload,
        ]);
    }

    private function resolveSourceFile(string $uid, int $fileId): ?File
    {
        $nodes = $this->rootFolder->getUserFolder($uid)->getById($fileId);
        if ($nodes === [] || !$nodes[0] instanceof File) {
            return null;
        }

        return $nodes[0];
    }
}
