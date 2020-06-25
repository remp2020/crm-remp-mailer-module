<?php

namespace Crm\RempMailerModule\Hermes;

use Crm\RempMailerModule\Models\Api\Client;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

class SendEmailHandler implements HandlerInterface
{
    use RetryTrait;

    private $apiClient;

    public function __construct(Client $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();

        $this->apiClient->sendEmail(
            $payload['email'],
            $payload['mail_template_code'],
            $payload['params'] ?? [],
            $payload['context'] ?? null,
            $payload['attachments'] ?? [],
            $payload['schedule_at'] ?? null
        );

        return true;
    }
}
