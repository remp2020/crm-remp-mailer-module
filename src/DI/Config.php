<?php

namespace Crm\RempMailerModule\DI;

class Config
{
    private $host;

    private $apiToken;

    public function setHost(string $host)
    {
        $this->host = $host;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setApiToken(string $apiToken)
    {
        $this->apiToken = $apiToken;
    }

    public function getApiToken(): string
    {
        return $this->apiToken;
    }
}
