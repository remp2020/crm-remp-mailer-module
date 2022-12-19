<?php

namespace Crm\RempMailerModule\Components\MailSettings;

interface MailSettingsControlFactoryInterface
{
    public function create(): MailSettings;
}
