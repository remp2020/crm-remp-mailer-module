<?php

namespace Crm\RempMailerModule\Repositories;

use Crm\RempMailerModule\Models\Api\Client;

class MailTypesRepository
{
    private $apiClient;

    public function __construct(Client $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    final public function all($publicListing = null): ?array
    {
        return $this->apiClient->getMailTypes(null, $publicListing);
    }

    final public function getByCode($code)
    {
        $mailTypes = $this->apiClient->getMailTypes($code);
        if (!$mailTypes) {
            return null;
        }
        return $mailTypes[0];
    }
}
