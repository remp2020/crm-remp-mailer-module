<?php

namespace Crm\RempMailerModule\Components\MailSettings;

interface MailSettingsControlFactoryInterface
{
    /** @return MailSettings */
    public function create();
}
