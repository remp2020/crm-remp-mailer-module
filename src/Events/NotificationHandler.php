<?php

namespace Crm\RempMailerModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\UsersModule\Events\NotificationEvent;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Tomaj\Hermes\Emitter;

class NotificationHandler extends AbstractListener
{
    private $hermesEmitter;

    public function __construct(Emitter $hermesEmitter)
    {
        $this->hermesEmitter = $hermesEmitter;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof NotificationEvent)) {
            throw new \Exception("Unable to handle event, expected NotificationEvent");
        }

        // We want to keep execution of delayed event in CRM as long as possible so we can always send fresh data
        // to mailer. Otherwise we could schedule an email and in the meantime user could change their information.

        $scheduleAt = null;
        if ($event->getScheduleAt()) {
            $scheduleAt = $event->getScheduleAt()->getTimestamp();
        }

        $attachments = [];
        foreach ($event->getAttachments() as $attachment) {
            $attachments[] = [
                'file' => $attachment['file'],
                'content' => base64_encode($attachment['content']),
            ];
        }

        $this->hermesEmitter->emit(new HermesMessage('mailer-send-email', [
            'email' => $event->getUser()->email,
            'mail_template_code' => $event->getTemplateCode(),
            'params' => $event->getParams(),
            'context' => $event->getContext(),
            'attachments' => $attachments,
            'locale' => $event->getLocale(),
        ], null, null, $scheduleAt));
    }
}
