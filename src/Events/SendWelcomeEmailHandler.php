<?php

namespace Crm\RempMailerModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Tomaj\Hermes\Emitter;

class SendWelcomeEmailHandler extends AbstractListener
{
    private $hermesEmitter;

    public function __construct(Emitter $hermesEmitter)
    {
        $this->hermesEmitter = $hermesEmitter;
    }

    public function handle(EventInterface $event)
    {
        $user = $event->getUser();

        if ($event->sendEmail()) {
            $this->hermesEmitter->emit(new HermesMessage('mailer-send-email', [
                'email' => $user->email,
                'mail_template_code' => 'welcome_email_with_password',
                'params' => [
                    'email' => $user->email,
                    'password' => $event->getOriginalPassword(),
                ],
            ]));
        }
    }
}
