<?php

namespace Crm\RempMailerModule\Models\User;

use Crm\ApplicationModule\Models\User\UserDataProviderInterface;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;

class MailSubscriptionsUserDataProvider implements UserDataProviderInterface
{
    public function __construct(
        private readonly MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
    ) {
    }

    public static function identifier(): string
    {
        return 'remp_mailer_subscriptions';
    }

    public function data($userId): ?array
    {
        $preferences = $this->mailUserSubscriptionsRepository->userPreferences($userId, true);

        $result = [];
        foreach ($preferences as $preference) {
            $preferenceData = [
                'code' => $preference['code'],
            ];
            foreach ($preference['variants'] ?? [] as $variant) {
                $preferenceData['variants'][] = $variant['code'];
            }
            $result[] = $preferenceData;
        }
        return $result;
    }

    public function download($userId)
    {
        // no need to export subscribed emails
        return [];
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
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
