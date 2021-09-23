<?php

namespace Crm\RempMailerModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\UsersModule\Events\UserCreatedEvent;
use Crm\UsersModule\Repository\UserEmailConfirmationsRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Application\LinkGenerator;
use Tomaj\Hermes\Emitter;

class SendWelcomeEmailHandler extends AbstractListener
{
    private $hermesEmitter;
    private $userEmailConfirmationsRepository;
    private $linkGenerator;
    
    public function __construct(
        Emitter $hermesEmitter,
        UserEmailConfirmationsRepository $userEmailConfirmationsRepository,
        LinkGenerator $linkGenerator
    ) {
        $this->hermesEmitter = $hermesEmitter;
        $this->userEmailConfirmationsRepository = $userEmailConfirmationsRepository;
        $this->linkGenerator = $linkGenerator;
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof UserCreatedEvent) {
            throw new \Exception("Unable to handle event, expected UserCreatedEvent, received [" . get_class($event) . "]");
        }
        
        if ($event->sendEmail()) {
            $user = $event->getUser();
            $token = $this->userEmailConfirmationsRepository->getToken($user->id);
            $link = $token ? $this->linkGenerator->link('Users:Users:EmailConfirm', [
                'token' => $token,
                'redirectUrl' => $user->referer,
            ]) : null;
            
            $this->hermesEmitter->emit(new HermesMessage('mailer-send-email', [
                'email' => $user->email,
                'mail_template_code' => 'welcome_email_with_password',
                'params' => [
                    'email' => $user->email,
                    'password' => $event->getOriginalPassword(),
                    'confirmation_url' => $link
                ],
                'attachments' => []
            ]));
        }
    }
}
