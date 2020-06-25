<?php

namespace Crm\RempMailerModule\Components\UserEmailSettings;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\RempMailerModule\Forms\EmailSettingsFormFactory;
use Kdyby\Translation\Translator;

class UserEmailSettingsWidget extends BaseWidget
{
    private $templateName = 'user_email_settings_widget.latte';

    private $emailSettingsFormFactory;

    private $translator;

    private $userId;

    public function __construct(WidgetManager $widgetManager, EmailSettingsFormFactory $emailSettingsFormFactory, Translator $translator)
    {
        parent::__construct($widgetManager);
        $this->emailSettingsFormFactory = $emailSettingsFormFactory;
        $this->translator = $translator;
    }

    public function header($id = '')
    {
        return $this->translator->translate('remp_mailer.admin.mail_settings.header');
    }

    public function identifier()
    {
        return 'mailer_useremailsettings';
    }

    public function render(int $userId)
    {
        $this->userId = $userId;

        $this->template->userId = $userId;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function createComponentUserEmailForm()
    {
        $form = $this->emailSettingsFormFactory->create($this->userId, true);
        $this->emailSettingsFormFactory->onUpdate = function () {
            $this->getPresenter()->flashMessage($this->translator->translate('remp_mailer.admin.mail_settings.actualized_message'));
        };
        return $form;
    }
}
