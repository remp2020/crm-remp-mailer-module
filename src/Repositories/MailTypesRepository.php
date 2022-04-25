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
        return $this->apiClient->getMailTypes(null, null, $publicListing);
    }

    final public function getByCode(string $code)
    {
        $mailTypes = $this->apiClient->getMailTypes([$code]);
        if (!$mailTypes) {
            return null;
        }
        return $mailTypes[0];
    }

    final public function getAllByCode(array $codes): array
    {
        $mailTypes = $this->apiClient->getMailTypes($codes);
        if (!$mailTypes) {
            return [];
        }
        return $mailTypes;
    }

    final public function getAllByCategoryCode(array $categoryCodes, $publicListing = null): array
    {
        $mailTypes = $this->apiClient->getMailTypes(null, $categoryCodes, $publicListing);
        if (!$mailTypes) {
            return [];
        }
        return $mailTypes;
    }
}
