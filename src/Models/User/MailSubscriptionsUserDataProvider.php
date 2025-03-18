<?php

namespace Crm\RempMailerModule\Models\User;

use Crm\ApplicationModule\Models\User\UserDataProviderInterface;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;

class MailSubscriptionsUserDataProvider implements UserDataProviderInterface
{
    private ?array $mailTypes = null;

    public function __construct(
        private readonly MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
        private readonly MailTypesRepository $mailTypesRepository,
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
        foreach ($preferences as $key => $preference) {
            $mailType = $this->getMailType($key);
            $preferenceData = [
                'code' => $mailType->code
            ];
            if (count($preference['variants'])) {
                $preferenceData['variants'] = $preference['variants'];
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

    private function getMailType($id)
    {
        if (!$this->mailTypes) {
            $types = $this->mailTypesRepository->all();
            $this->mailTypes = [];
            foreach ($types as $type) {
                $this->mailTypes[$type->id] = $type;
            }
        }
        return $this->mailTypes[$id];
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
