<?php

namespace Crm\RempMailerModule\Events;

use League\Event\AbstractEvent;

class ChangeUserNewsletterSubscriptionsEvent extends AbstractEvent
{
    private $userId;

    private $changes = [];

    public function __construct($userId, array $subscribeMailTypeCodes, array $unSubscribeMailTypeCodes)
    {
        $this->userId = $userId;

        foreach ($subscribeMailTypeCodes as $subscribeMailTypeCode) {
            $this->changes[$subscribeMailTypeCode] = true;
        }

        foreach ($unSubscribeMailTypeCodes as $unSubscribeMailTypeCode) {
            $this->changes[$unSubscribeMailTypeCode] = false;
        }
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }
}
