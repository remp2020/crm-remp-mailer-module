<?php

namespace Crm\RempMailerModule\Repositories;

use Crm\RempMailerModule\Models\Api\Client;
use Crm\RempMailerModule\Models\Api\MailLogQueryBuilder;

class MailLogsRepository
{
    private $apiClient;

    public function __construct(Client $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    final public function get(MailLogQueryBuilder $queryBuilder)
    {
        return $this->apiClient->getMailLogs(
            $queryBuilder->getEmail(),
            $queryBuilder->getFilter(),
            $queryBuilder->getLimit(),
            $queryBuilder->getPage(),
            $queryBuilder->getMailTemplateIds(),
            $queryBuilder->getMailTemplateCodes(),
        );
    }

    final public function count(string $email, array $statuses, ?\DateTime $from = null, ?\DateTime $to = null)
    {
        return $this->apiClient->countMailLogs($email, $statuses, $from, $to);
    }
}
