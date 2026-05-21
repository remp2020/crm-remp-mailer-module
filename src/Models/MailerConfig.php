<?php

namespace Crm\RempMailerModule\Models;

class MailerConfig
{
    private bool $subscribeOnlyConfirmedUser = false;

    private ?int $recentlyConfirmedWindowSeconds = null;

    public function setSubscribeOnlyConfirmedUser(bool $subscribeOnlyConfirmedUser): void
    {
        $this->subscribeOnlyConfirmedUser = $subscribeOnlyConfirmedUser;
    }

    public function getSubscribeOnlyConfirmedUser(): bool
    {
        return $this->subscribeOnlyConfirmedUser;
    }

    public function setRecentlyConfirmedWindowSeconds(int $seconds): void
    {
        $this->recentlyConfirmedWindowSeconds = $seconds;
    }

    public function getRecentlyConfirmedWindowSeconds(): ?int
    {
        return $this->recentlyConfirmedWindowSeconds;
    }
}
