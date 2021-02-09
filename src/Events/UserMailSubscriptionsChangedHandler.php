<?php

namespace Crm\RempMailerModule\Events;

use Crm\UsersModule\User\UserData;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class UserMailSubscriptionsChangedHandler extends AbstractListener
{
    private $userData;

    public function __construct(UserData $userData)
    {
        $this->userData = $userData;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof UserMailSubscriptionsChanged)) {
            throw new \Exception('invalid type of event received: ' . get_class($event));
        }

        $this->userData->refreshUserTokens($event->getUserId());
    }
}
