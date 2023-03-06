<?php

namespace Crm\RempMailerModule\Components\MailSettings;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\RempMailerModule\Forms\EmailSettingsFormFactory;
use Crm\RempMailerModule\Models\Api\MailSubscribeRequest;
use Crm\RempMailerModule\Repositories\MailTypeCategoriesRepository;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;
use Crm\UsersModule\Auth\UserManager;
use Nette\Application\UI\Control;
use Nette\Localization\Translator;

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

    private $translator;

    public function __construct(
        EmailSettingsFormFactory $emailSettingsFormFactory,
        MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
        MailTypeCategoriesRepository $mailTypeCategoriesRepository,
        MailTypesRepository $mailTypesRepository,
        UserManager $userManager,
        Translator $translator
    ) {
        $this->emailSettingsFormFactory = $emailSettingsFormFactory;
        $this->mailUserSubscriptionsRepository = $mailUserSubscriptionsRepository;
        $this->mailTypesRepository = $mailTypesRepository;
        $this->mailTypeCategoriesRepository = $mailTypeCategoriesRepository;
        $this->userManager = $userManager;
        $this->translator = $translator;
    }

    public function render(array $mailTypeCategoryCodes = null)
    {
        $this->template->setFile(__DIR__ . '/' . $this->view);

        $categories = $this->mailTypeCategoriesRepository->all();
        $this->template->categories = $categories;

        if ($categories === null) {
            $this->template->showUnsubscribeAll = false;
            $this->template->showSubscribeAll = false;
            $this->template->render();
            return;
        }

        if ($mailTypeCategoryCodes) {
            $mailTypes = $this->mailTypesRepository->getAllByCategoryCode($mailTypeCategoryCodes, true);
        } else {
            $mailTypes = $this->mailTypesRepository->all(true);
        }

        $this->template->types = $mailTypes;

        $mappedMailTypes = [];
        foreach ($mailTypes as $mailType) {
            $mappedMailTypes[$mailType->id] = $mailType;
        }

        $userSubscriptions = [];
        if ($this->presenter->getUser()->isLoggedIn()) {
            $userSubscriptions = $this->mailUserSubscriptionsRepository->userPreferences($this->presenter->getUser()->id);
        } else {
            $this->template->notLogged = true;
        }

        $showUnsubscribeAll = false;
        $showSubscribeAll = false;

        foreach ($userSubscriptions as $mailTypeId => $userSubscription) {
            if (isset($mappedMailTypes[$mailTypeId]) && $mappedMailTypes[$mailTypeId]->locked) {
                continue;
            }

            if ($userSubscription['is_subscribed']) {
                $showUnsubscribeAll = true;
            } else {
                $showSubscribeAll = true;
            }
        }

        $this->template->mailTypeCategoryCodes = $mailTypeCategoryCodes;
        $this->template->showUnsubscribeAll = $showUnsubscribeAll;
        $this->template->showSubscribeAll = $showSubscribeAll;
        $this->template->userSubscriptions = $userSubscriptions;

        $this->template->render();
    }


    public function handleSubscribe($id, $variantId = null)
    {
        $this->presenter->onlyLoggedIn();
        $user = $this->userManager->loadUser($this->presenter->getUser());

        $msr = (new MailSubscribeRequest)
            ->setMailTypeId($id)
            ->setUser($user);
        if ($variantId) {
            $msr->setVariantId($variantId);
        }

        $this->mailUserSubscriptionsRepository->subscribe($msr, $this->presenter->rtmParams());
        $this->flashMessage($this->translator->translate('remp_mailer.frontend.mail_settings.subscribe_success'));
        $this->template->changedId = (int)$id;

        if ($this->presenter->isAjax()) {
            $this->redrawControl('data-wrapper');
            $this->redrawControl('buttons');
            $this->redrawControl('mail-type-' . $id);
        } else {
            $this->presenter->redirect('this');
        }
    }

    public function handleUnSubscribe($id)
    {
        $this->presenter->onlyLoggedIn();
        $user = $this->userManager->loadUser($this->presenter->getUser());

        $msr = (new MailSubscribeRequest)
            ->setMailTypeId($id)
            ->setUser($user);

        $this->mailUserSubscriptionsRepository->unsubscribe($msr, $this->presenter->rtmParams());
        $this->flashMessage($this->translator->translate('remp_mailer.frontend.mail_settings.unsubscribe_success'));
        $this->template->changedId = (int)$id;

        if ($this->presenter->isAjax()) {
            $this->redrawControl('data-wrapper');
            $this->redrawControl('buttons');
            $this->redrawControl('mail-type-' . $id);
        } else {
            $this->presenter->redirect('this');
        }
    }

    public function handleAllSubscribe(array $cat = null)
    {
        $this->presenter->onlyLoggedIn();

        $user = $this->userManager->loadUser($this->presenter->user);

        if ($cat) {
            $mailTypes = $this->mailTypesRepository->getAllByCategoryCode($cat, true);
            $msrs = [];
            foreach ($mailTypes as $mailType) {
                if ($mailType->locked) {
                    continue;
                }
                $msr = (new MailSubscribeRequest)
                    ->setSubscribed(true)
                    ->setMailTypeId($mailType->id)
                    ->setMailTypeCode($mailType->code)
                    ->setUser($user)
                    ->setSendAccompanyingEmails(false);
                $msrs[] = $msr;
            }
            $this->mailUserSubscriptionsRepository->bulkSubscriptionChange($msrs);
        } else {
            $this->mailUserSubscriptionsRepository->subscribeUserAll($user);
        }

        $this->flashMessage($this->translator->translate('remp_mailer.frontend.mail_settings.subscribe_success'));
        if ($this->presenter->isAjax() && !$cat) {
            $this->redrawControl('data-wrapper');
            $this->redrawControl('buttons');
        } else {
            $this->presenter->redirect('this');
        }
    }

    public function handleAllUnSubscribe(array $cat = null)
    {
        $this->presenter->onlyLoggedIn();
        $user = $this->userManager->loadUser($this->presenter->user);

        if ($cat) {
            $mailTypes = $this->mailTypesRepository->getAllByCategoryCode($cat, true);
            $msrs = [];
            foreach ($mailTypes as $mailType) {
                if ($mailType->locked) {
                    continue;
                }
                $msr = (new MailSubscribeRequest)
                    ->setSubscribed(false)
                    ->setMailTypeId($mailType->id)
                    ->setMailTypeCode($mailType->code)
                    ->setUser($user)
                    ->setSendAccompanyingEmails(false);
                $msrs[] = $msr;
            }
            $this->mailUserSubscriptionsRepository->bulkSubscriptionChange($msrs);
        } else {
            $this->mailUserSubscriptionsRepository->unsubscribeUserAll($user);
        }

        $this->flashMessage($this->translator->translate('remp_mailer.frontend.mail_settings.unsubscribe_success'));
        if ($this->presenter->isAjax() && !$cat) {
            $this->redrawControl('data-wrapper');
            $this->redrawControl('buttons');
        } else {
            $this->presenter->redirect('this');
        }
    }

    public function createComponentEmailSettingsForm()
    {
        $form = $this->emailSettingsFormFactory->create($this->presenter->getUser()->getId());

        $this->emailSettingsFormFactory->onUpdate = function () {
            $this->flashMessage($this->translator->translate('remp_mailer.frontend.mail_settings.actualized_message'));
            $this->presenter->redirect('this');
        };

        return $form;
    }
}
