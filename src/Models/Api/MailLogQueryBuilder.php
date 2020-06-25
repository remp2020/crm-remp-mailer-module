<?php

namespace Crm\RempMailerModule\Models\Api;

class MailLogQueryBuilder
{
    private $filter;
    private $limit = 300;
    private $page;
    private $email;
    private $mailTemplateIds;

    public function setFilter(string $filter)
    {
        $this->filter = $filter;
        return $this;
    }

    public function setLimit(int $limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function setPage(int $page)
    {
        $this->page = $page;
        return $this;
    }

    public function setEmail(string $email)
    {
        $this->email = $email;
        return $this;
    }

    public function setMailTemplateIds(array $ids)
    {
        $this->mailTemplateIds = $ids;
        return $this;
    }

    public function getMailTemplateIds()
    {
        return $this->mailTemplateIds;
    }

    public function getFilter()
    {
        return $this->filter;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function getPage()
    {
        return $this->page;
    }

    public function getEmail()
    {
        return $this->email;
    }
}
