<?php

namespace Crm\RempMailerModule\Models\User;

use Crm\ApplicationModule\User\UserDataProviderInterface;
use Crm\RempMailerModule\Models\Api\Client;
use Crm\UsersModule\Repository\UsersRepository;

class RempMailerUserDataProvider implements UserDataProviderInterface
{
    private $apiClient;

    private $usersRepository;

    public function __construct(Client $apiClient, UsersRepository $usersRepository)
    {
        $this->apiClient = $apiClient;
        $this->usersRepository = $usersRepository;
    }

    public static function identifier(): string
    {
        return 'remp_mailer';
    }

    public function data($userId)
    {
        return [];
    }

    public function download($userId)
    {
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
