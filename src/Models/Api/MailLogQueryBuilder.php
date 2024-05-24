<?php

namespace Crm\RempMailerModule\Models\Api;

use Nette\Utils\DateTime;

class MailLogQueryBuilder
{
    private ?array $filter = null;
    private int $limit = 300;
    private int $page;
    private string $email;
    private array $mailTemplateIds = [];
    private array $mailTemplateCodes = [];

    public function setFilter(string $filterBy, ?DateTime $from = null, ?DateTime $to = null): self
    {
        $timeFrame = array_filter([
            'from' => $from,
            'to' => $to,
        ]);
        if (!empty($timeFrame)) {
            $this->filter[$filterBy] = $timeFrame;
        }
        return $this;
    }

    public function setLimit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function setPage(int $page): self
    {
        $this->page = $page;
        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function setMailTemplateIds(array $ids): self
    {
        $this->mailTemplateIds = $ids;
        return $this;
    }

    public function getMailTemplateIds(): array
    {
        return $this->mailTemplateIds;
    }

    public function setMailTemplateCodes(array $codes): self
    {
        $this->mailTemplateCodes = $codes;
        return $this;
    }

    public function getMailTemplateCodes(): array
    {
        return $this->mailTemplateCodes;
    }

    public function getFilter(): ?array
    {
        return $this->filter;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
