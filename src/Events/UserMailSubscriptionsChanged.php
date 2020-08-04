<?php

namespace Crm\RempMailerModule\Events;

use League\Event\AbstractEvent;

class UserMailSubscriptionsChanged extends AbstractEvent
{
    private $userId;

    private $mailTypeId;

    public function __construct($userId, $mailTypeId)
    {
        $this->userId = $userId;
        $this->mailTypeId = $mailTypeId;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getMailTypeId()
    {
        return $this->mailTypeId;
    }
}
