<?php

namespace Crm\RempMailerModule\Hermes;

use Crm\RempMailerModule\Models\Api\Client;
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

    public function __construct(
        Client $apiClient
    ) {
        $this->apiClient = $apiClient;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();

        if (!Validators::isEmail($payload['email'])) {
            Debugger::log('Attempt to send email through REMP Mailer with invalid email address: ' . $payload['email'], ILogger::WARNING);
            return false;
        }

        $mailTemplate = $this->apiClient->getTemplate($payload['mail_template_code']);
        if (!$mailTemplate) {
            Debugger::log("Could not load mail template: record with code [{$payload['mail_template_code']}] doesn't exist");
            return false;
        }

        $attachments = $mailTemplate->attachments_enabled ? $payload['attachments'] : [];

        $this->apiClient->sendEmail(
            $payload['email'],
            $payload['mail_template_code'],
            $payload['params'] ?? [],
            $payload['context'] ?? null,
            $attachments,
            $payload['schedule_at'] ?? null
        );

        return true;
    }
}
