<?php

namespace Crm\RempMailerModule\Repositories;

use Crm\RempMailerModule\Models\Api\Client;

class MailTemplatesRepository
{
    private $apiClient;

    public function __construct(Client $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    final public function all(array $mailTypeCodes = [], bool $withMailTypes = false): ?array
    {
        return $this->apiClient->getTemplates($mailTypeCodes, $withMailTypes);
    }
}
