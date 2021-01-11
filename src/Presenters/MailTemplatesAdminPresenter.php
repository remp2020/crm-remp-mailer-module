<?php

namespace Crm\RempMailerModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\RempMailerModule\DI\Config;

class MailTemplatesAdminPresenter extends FrontendPresenter
{
    public $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    public function actionShow($code)
    {
        $this->redirectUrl("{$this->config->getHost()}/template/show-by-code/{$code}");
    }
}
