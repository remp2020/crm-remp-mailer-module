<?php

namespace Crm\RempMailerModule\Repositories;

use Crm\RempMailerModule\Models\Api\Client;

class MailTypesRepository
{
    public function __construct(private Client $apiClient)
    {
    }

    final public function all($publicListing = null, bool $includeVariantsData = false): ?array
    {
        return $this->apiClient->getMailTypes(publicListing: $publicListing, includeVariantsData: $includeVariantsData);
    }

    final public function getByCode(string $code, bool $includeVariantsData = false)
    {
        $mailTypes = $this->apiClient->getMailTypes(codes: [$code], includeVariantsData: $includeVariantsData);
        if (!$mailTypes) {
            return null;
        }
        return $mailTypes[0];
    }

    final public function getAllByCode(array $codes, bool $includeVariantsData = false): array
    {
        $mailTypes = $this->apiClient->getMailTypes(codes: $codes, includeVariantsData: $includeVariantsData);
        if (!$mailTypes) {
            return [];
        }
        return $mailTypes;
    }

    final public function getAllByCategoryCode(
        array $categoryCodes,
        $publicListing = null,
        bool $includeVariantsData = false,
    ): array {
        $mailTypes = $this->apiClient->getMailTypes(
            categoryCodes: $categoryCodes,
            publicListing: $publicListing,
            includeVariantsData: $includeVariantsData,
        );
        if (!$mailTypes) {
            return [];
        }
        return $mailTypes;
    }
}
