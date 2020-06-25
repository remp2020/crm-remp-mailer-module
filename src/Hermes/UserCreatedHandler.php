<?php

namespace Crm\RempMailerModule\Hermes;

use Crm\RempMailerModule\Models\Api\Client;
use Crm\UsersModule\Repository\UsersRepository;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class UserCreatedHandler implements HandlerInterface
{
    private $usersRepository;

    private $apiClient;

    public function __construct(UsersRepository $usersRepository, Client $apiClient)
    {
        $this->usersRepository = $usersRepository;
        $this->apiClient = $apiClient;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!isset($payload['user_id'])) {
            throw new \Exception('unable to handle event: user_id missing');
        }

        $user = $this->usersRepository->find($payload['user_id']);
        $this->apiClient->userRegistered($user->email, $user->id);

        return true;
    }
}
