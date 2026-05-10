<?php

declare(strict_types=1);

namespace OCA\NCExtrak\Notification;

use OCA\NCExtrak\AppInfo\Application;
use OCP\IL10N;
use OCP\IL10NFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier
{
    public function __construct(private IL10NFactory $l10nFactory)
    {
    }

    public function getID(): string
    {
        return Application::APP_ID;
    }

    public function getName(): string
    {
        return 'NCExtrak notifier';
    }

    public function prepare(INotification $notification, string $languageCode): INotification
    {
        $l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
        $subject = $notification->getSubject();
        $params = $notification->getSubjectParameters();

        if ($subject === 'extract_done') {
            $targetFolder = is_string($params[0] ?? null) ? $params[0] : '';
            $notification->setParsedSubject(
                $l->t('Archive extracted to folder %s', [$targetFolder]),
            );
            return $notification;
        }

        if ($subject === 'extract_failed') {
            $error = is_string($params[0] ?? null) ? $params[0] : '';
            $notification->setParsedSubject(
                $l->t('Archive extraction failed: %s', [$error]),
            );
            return $notification;
        }

        throw new \InvalidArgumentException('Unsupported notification subject');
    }
}
