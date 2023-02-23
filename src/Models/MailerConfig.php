<?php

namespace Crm\RempMailerModule\Models;

class MailerConfig
{
    private bool $subscribeOnlyConfirmedUser = false;

    public function setSubscribeOnlyConfirmedUser(bool $subscribeOnlyConfirmedUser): void
    {
        $this->subscribeOnlyConfirmedUser = $subscribeOnlyConfirmedUser;
    }

    public function getSubscribeOnlyConfirmedUser(): bool
    {
        return $this->subscribeOnlyConfirmedUser;
    }
}
