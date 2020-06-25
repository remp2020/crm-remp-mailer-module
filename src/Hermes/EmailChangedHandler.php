<?php

namespace Crm\RempMailerModule\Hermes;

use Crm\RempMailerModule\Models\Api\Client;
use Crm\UsersModule\Repository\UsersRepository;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

class EmailChangedHandler implements HandlerInterface
{
    use RetryTrait;

    private $usersRepository;

    private $trackerApi;

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
        if (!isset($payload['original_email'])) {
            throw new \Exception('unable to handle event: original_email missing');
        }
        if (!isset($payload['new_email'])) {
            throw new \Exception('unable to handle event: new_email missing');
        }

        $user = $this->usersRepository->find($payload['user_id']);
        if ($user->deleted_at) {
            // let's not handle this for deleted users
            return true;
        }

        $this->apiClient->emailChanged($payload['original_email'], $payload['new_email']);
        return true;
    }
}
