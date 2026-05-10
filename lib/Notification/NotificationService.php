<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Notification;

use DateTimeImmutable;
use OCA\NCExtrak\AppInfo\Application;
use OCP\Notification\IManager;

class NotificationService
{
    public function __construct(private IManager $notificationManager)
    {
    }

    public function notifySuccess(string $uid, int $jobId, string $targetFolder): void
    {
        $notification = $this->notificationManager->createNotification();
        $notification->setApp(Application::APP_ID);
        $notification->setUser($uid);
        $notification->setDateTime(new DateTimeImmutable());
        $notification->setObject('ncextrak_job', (string) $jobId);
        $notification->setSubject('extract_done', [$targetFolder]);

        $this->notificationManager->notify($notification);
    }

    public function notifyFailure(string $uid, int $jobId, string $error): void
    {
        $notification = $this->notificationManager->createNotification();
        $notification->setApp(Application::APP_ID);
        $notification->setUser($uid);
        $notification->setDateTime(new DateTimeImmutable());
        $notification->setObject('ncextrak_job', (string) $jobId);
        $notification->setSubject('extract_failed', [$error]);

        $this->notificationManager->notify($notification);
    }
}
