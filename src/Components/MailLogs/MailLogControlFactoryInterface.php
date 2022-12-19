<?php

namespace Crm\RempMailerModule\Components\MailLogs;

interface MailLogControlFactoryInterface
{
    public function create(): MailLogs;
}
