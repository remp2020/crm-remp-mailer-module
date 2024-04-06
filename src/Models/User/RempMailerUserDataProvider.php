<?php

namespace Crm\RempMailerModule\Models\User;

use Crm\ApplicationModule\Models\User\UserDataProviderInterface;
use Crm\RempMailerModule\Models\Api\Client;
use Crm\RempMailerModule\Models\Api\MailLogQueryBuilder;
use Crm\RempMailerModule\Repositories\MailLogsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use DateTimeInterface;

class RempMailerUserDataProvider implements UserDataProviderInterface
{
    public function __construct(
        private Client $apiClient,
        private UsersRepository $usersRepository,
        private MailLogsRepository $mailLogsRepository,
    ) {
    }

    public static function identifier(): string
    {
        return 'remp_mailer';
    }

    public function data($userId): ?array
    {
        return null;
    }

    public function download($userId)
    {
        $user = $this->usersRepository->find($userId);

        $logQuery = new MailLogQueryBuilder();
        $logQuery->setEmail($user->email);

        $emailLogs = $this->mailLogsRepository->get($logQuery);

        $result = [];
        foreach ($emailLogs ?? [] as $log) {
            $result[] = array_filter([
                'email' => $log->email,
                'subject' => $log->subject,
                'sent_at' => $log->sent_at->format(DateTimeInterface::RFC3339),
                'delivered_at' => $log->delivered_at?->format(DateTimeInterface::RFC3339),
                'opened_at' => $log->opened_at?->format(DateTimeInterface::RFC3339),
                'clicked_at' => $log->clicked_at?->format(DateTimeInterface::RFC3339),
                'spam_complained_at' => $log->spam_complained_at?->format(DateTimeInterface::RFC3339),
                'hard_bounced_at' => $log->hard_bounced_at?->format(DateTimeInterface::RFC3339),
            ]);
        }

        return $result;
    }

    public function downloadAttachments($userId)
    {
        return [];
    }

    public function protect($userId): array
    {
        return [];
    }

    public function delete($userId, $protectedData = [])
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            return;
        }

        $this->apiClient->userDelete($user->email);
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
