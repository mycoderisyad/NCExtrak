<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class JobMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'ncextrak_jobs', Job::class);
    }

    public function createQueued(string $uid, int $sourceFileId, bool $overwrite): Job
    {
        $job = new Job();
        $timestamp = time();
        $job->setUid($uid);
        $job->setSourceFileId($sourceFileId);
        $job->setOverwrite($overwrite);
        $job->setState('queued');
        $job->setProgress(0);
        $job->setCreatedAt($timestamp);
        $job->setUpdatedAt($timestamp);

        return $this->insert($job);
    }

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function findById(int $jobId): Job
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($jobId)));

        return $this->findEntity($qb);
    }

    /**
     * @throws DoesNotExistException
     * @throws MultipleObjectsReturnedException
     */
    public function findByIdForUser(int $jobId, string $uid): Job
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($jobId)))
            ->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));

        return $this->findEntity($qb);
    }

    public function markRunning(Job $job): Job
    {
        $job->setState('running');
        $job->setUpdatedAt(time());
        return $this->update($job);
    }

    public function updateProgress(Job $job, int $progress): Job
    {
        $clamped = max(0, min(99, $progress));
        if ($clamped === $job->getProgress()) {
            return $job;
        }
        $job->setProgress($clamped);
        $job->setUpdatedAt(time());
        return $this->update($job);
    }

    public function markDone(Job $job, string $resultPayload): Job
    {
        $job->setState('done');
        $job->setProgress(100);
        $job->setResultPayload($resultPayload);
        $job->setUpdatedAt(time());
        return $this->update($job);
    }

    public function markFailed(Job $job, string $error): Job
    {
        $job->setState('failed');
        $job->setError($error);
        $job->setUpdatedAt(time());
        return $this->update($job);
    }
}
