<?php

namespace Crm\RempMailerModule\Components\MailSettings;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\RempMailerModule\Forms\EmailSettingsFormFactory;
use Crm\RempMailerModule\Repositories\MailTypeCategoriesRepository;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;
use Crm\UsersModule\Auth\UserManager;
use Nette\Application\UI\Control;

/**
 * @property FrontendPresenter $presenter
 */
class MailSettings extends Control
{
    private $view = 'mail_settings.latte';

    private $emailSettingsFormFactory;

    private $mailUserSubscriptionsRepository;

    private $mailTypeCategoriesRepository;

    private $mailTypesRepository;

    private $userManager;

    public function __construct(
        EmailSettingsFormFactory $emailSettingsFormFactory,
        MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
        MailTypeCategoriesRepository $mailTypeCategoriesRepository,
        MailTypesRepository $mailTypesRepository,
        UserManager $userManager
    ) {
        parent::__construct();
        $this->emailSettingsFormFactory = $emailSettingsFormFactory;
        $this->mailUserSubscriptionsRepository = $mailUserSubscriptionsRepository;
        $this->mailTypesRepository = $mailTypesRepository;
        $this->mailTypeCategoriesRepository = $mailTypeCategoriesRepository;
        $this->userManager = $userManager;
    }

    public function render()
    {
        $categories = $this->mailTypeCategoriesRepository->all();
        $this->template->categories = $categories;

        $types = $this->mailTypesRepository->all(true);
        $this->template->types = $types;

        $userSubscriptions = [];
        if ($this->presenter->getUser()->isLoggedIn()) {
            $userSubscriptions = $this->mailUserSubscriptionsRepository->userPreferences($this->presenter->getUser()->id);
        } else {
            $this->template->notLogged = true;
        }

        $showUnsubsribeAll = false;
        $showSubscribeAll = false;

        foreach ($userSubscriptions as $typeId => $userSubscription) {
            foreach ($types as $type) {
                if ($type->id == $typeId && $type->locked) {
                    continue 2;
                }
            }
            if (!$userSubscription['is_subscribed']) {
                $showSubscribeAll = true;
            }
            if ($userSubscription['is_subscribed']) {
                $showUnsubsribeAll = true;
            }
        }

        $this->template->showUnsubsribeAll = $showUnsubsribeAll;
        $this->template->showSubscribeAll = $showSubscribeAll;

        $this->template->userSubscriptions = $userSubscriptions;


        $this->template->setFile(__DIR__ . '/' . $this->view);
        $this->template->render();
    }


    public function handleSubscribe($id, $variantId = null)
    {
        if (!$this->presenter->getUser()->isLoggedIn()) {
            $this->presenter->redirect(':Mailer:Register:email', $id, $variantId);
        }
        $this->mailUserSubscriptionsRepository->subscribeUser($this->presenter->getUser()->getIdentity(), $id, $variantId, $this->presenter->utmParams());
        $this->flashMessage($this->presenter->translator->translate('remp_mailer.frontend.mail_settings.subscribe_success'));
        $this->template->changedId = (int)$id;

        if ($this->presenter->isAjax()) {
            $this->redrawControl('data-wrapper');
            $this->redrawControl('buttons');
            $this->redrawControl('mail-type-' . $id);
        } else {
            $this->presenter->redirect(':Mailer:MailSettings:mailSettings');
        }
    }

    public function handleUnSubscribe($id)
    {
        $this->getPresenter()->onlyLoggedIn();
        $this->mailUserSubscriptionsRepository->unSubscribeUser($this->presenter->getUser()->getIdentity(), $id, $this->presenter->utmParams());
        $this->flashMessage($this->presenter->translator->translate('remp_mailer.frontend.mail_settings.unsubscribe_success'));
        $this->template->changedId = (int)$id;

        if ($this->presenter->isAjax()) {
            $this->redrawControl('data-wrapper');
            $this->redrawControl('buttons');
            $this->redrawControl('mail-type-' . $id);
        } else {
            $this->presenter->redirect(':Mailer:MailSettings:mailSettings');
        }
    }

    public function handleAllSubscribe()
    {
        if (!$this->presenter->getUser()->isLoggedIn()) {
            $this->presenter->redirect(':Mailer:Register:email');
        }

        $user = $this->userManager->loadUser($this->presenter->user);
        $this->mailUserSubscriptionsRepository->subscribeUserAll($user);

        $this->flashMessage($this->presenter->translator->translate('remp_mailer.frontend.mail_settings.subscribe_success'));
        if ($this->presenter->isAjax()) {
            $this->redrawControl('data-wrapper');
        } else {
            $this->presenter->redirect(':Mailer:MailSettings:mailSettings');
        }
    }

    public function handleAllUnSubscribe()
    {
        $this->presenter->onlyLoggedIn();
        $user = $this->userManager->loadUser($this->presenter->user);

        $this->mailUserSubscriptionsRepository->unsubscribeUserAll($user);

        $this->flashMessage($this->presenter->translator->translate('remp_mailer.frontend.mail_settings.unsubscribe_success'));
        if ($this->presenter->isAjax()) {
            $this->redrawControl('data-wrapper');
        } else {
            $this->presenter->redirect(':Mailer:MailSettings:mailSettings');
        }
    }

    public function createComponentEmailSettingsForm()
    {
        $form = $this->emailSettingsFormFactory->create($this->presenter->getUser()->getId());

        $this->emailSettingsFormFactory->onUpdate = function () {
            $this->flashMessage('Nastavenia boli aktualizované');
            $this->presenter->redirect(':Mailer:MailSettings:mailSettings');
        };

        return $form;
    }
}
