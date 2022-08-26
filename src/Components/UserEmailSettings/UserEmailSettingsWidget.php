<?php

namespace Crm\RempMailerModule\Components\UserEmailSettings;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\RempMailerModule\Forms\EmailSettingsFormFactory;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\User\UnclaimedUser;

class UserEmailSettingsWidget extends BaseLazyWidget
{
    private $templateName = 'user_email_settings_widget.latte';

    private $emailSettingsFormFactory;

    private $translator;

    private $userId;

    private $usersRepository;

    private $unclaimedUser;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        EmailSettingsFormFactory $emailSettingsFormFactory,
        Translator $translator,
        UsersRepository $usersRepository,
        UnclaimedUser $unclaimedUser
    ) {
        parent::__construct($lazyWidgetManager);
        $this->emailSettingsFormFactory = $emailSettingsFormFactory;
        $this->translator = $translator;
        $this->usersRepository = $usersRepository;
        $this->unclaimedUser = $unclaimedUser;
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
        $user = $this->usersRepository->find($userId);

        $this->template->userId = $userId;
        $this->template->isAnonymous = $user->deleted_at || $this->unclaimedUser->isUnclaimedUser($user);
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
