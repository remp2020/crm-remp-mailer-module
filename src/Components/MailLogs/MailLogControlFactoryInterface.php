<?php

namespace Crm\RempMailerModule\Components\MailLogs;

interface MailLogControlFactoryInterface
{
    /** @return MailLogs */
    public function create();
}
