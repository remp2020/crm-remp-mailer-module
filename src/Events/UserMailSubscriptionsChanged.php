<?php

namespace Crm\RempMailerModule\Events;

use League\Event\AbstractEvent;

class UserMailSubscriptionsChanged extends AbstractEvent
{
    public const SUBSCRIBED = 'subscribed';
    public const UNSUBSCRIBED = 'unsubscribed';

    private $userId;

    private $mailTypeId;

    private $subscribed;

    /**
     * @param int $userId
     * @param int $mailTypeId
     * @param string $subscribed Change of user's mail subscription. Allowed values UserMailSubscriptionsChanged::SUBSCRIBED / UserMailSubscriptionsChanged::UNSUBSCRIBED.
     * @throws \Exception
     */
    public function __construct(int $userId, int $mailTypeId, string $subscribed)
    {
        $this->userId = $userId;
        $this->mailTypeId = $mailTypeId;

        if (!in_array($subscribed, [self::SUBSCRIBED, self::UNSUBSCRIBED], true)) {
            throw new \Exception("User can subscribe or unsubscribe. [{$subscribed}] received.");
        }
        $this->subscribed = $subscribed;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getMailTypeId()
    {
        return $this->mailTypeId;
    }

    public function getSubscribed()
    {
        return $this->subscribed;
    }
}
