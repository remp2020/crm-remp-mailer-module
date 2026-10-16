<?php

namespace Crm\RempMailerModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\RempMailerModule\DI\Config;

class MailTypesAdminPresenter extends FrontendPresenter
{
    public $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    public function actionShow($id)
    {
        $this->redirectUrl("{$this->config->getHost()}/mailer/list/show/{$id}");
    }
}
