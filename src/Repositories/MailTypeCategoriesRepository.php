<?php

namespace Crm\RempMailerModule\Repositories;

use Crm\RempMailerModule\Models\Api\Client;

class MailTypeCategoriesRepository
{
    private $apiClient;

    public function __construct(Client $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    final public function all()
    {
        return $this->apiClient->getAllCategories();
    }
}
