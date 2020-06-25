<?php

namespace Crm\RempMailerModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\RempMailerModule\DI\Config;

class MailTemplatesAdminPresenter extends FrontendPresenter
{
    /** @var Config @inject */
    public $config;

    public function actionShow($id)
    {
        $this->redirectUrl("{$this->config->getHost()}/template/show/{$id}");
    }
}
