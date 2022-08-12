<?php

namespace Crm\RempMailerModule\Hermes;

use Crm\RempMailerModule\Models\Api\Client;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\Validators;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;
use Tracy\Debugger;
use Tracy\ILogger;

class SendEmailHandler implements HandlerInterface
{
    use RetryTrait;

    private $apiClient;

    private $usersRepository;

    public function __construct(
        Client $apiClient,
        UsersRepository $usersRepository
    ) {
        $this->apiClient = $apiClient;
        $this->usersRepository = $usersRepository;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();

        if (!Validators::isEmail($payload['email'])) {
            Debugger::log('Attempt to send email through REMP Mailer with invalid email address: ' . $payload['email'], ILogger::WARNING);
            return false;
        }

        $user = $this->usersRepository->getByEmail($payload['email']);
        if ($user && !$user->active) {
            Debugger::log('Attempt to send email through REMP Mailer with inactive email address: ' . $payload['email'], ILogger::WARNING);
            return false;
        }

        $mailTemplate = $this->apiClient->getTemplate($payload['mail_template_code']);
        if (!$mailTemplate) {
            Debugger::log("Could not load mail template: record with code [{$payload['mail_template_code']}] doesn't exist");
            return false;
        }

        $attachments = $payload['attachments'] ?? [];
        if (isset($mailTemplate->attachments_enabled) && $mailTemplate->attachments_enabled === false) {
            // If mailer explicitly doesn't allow attachments, don't include it. If the flag is not set, it's probably
            // older version of Mailer without the flag. In that case allow the attachments.
            $attachments = [];
        }

        $this->apiClient->sendEmail(
            $payload['email'],
            $payload['mail_template_code'],
            $payload['params'] ?? [],
            $payload['context'] ?? null,
            $attachments,
            $payload['schedule_at'] ?? null,
            $payload['locale'] ?? $user->locale ?? null
        );

        return true;
    }
}
