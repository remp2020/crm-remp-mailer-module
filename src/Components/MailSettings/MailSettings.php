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
    private string $view = 'mail_settings.latte';

    public function __construct(
        private EmailSettingsFormFactory $emailSettingsFormFactory,
        private MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
        private MailTypeCategoriesRepository $mailTypeCategoriesRepository,
        private MailTypesRepository $mailTypesRepository,
        private UserManager $userManager,
        private Translator $translator
    ) {
    }

    public function render(array $mailTypeCategoryCodes = null)
    {
        $this->template->setFile(__DIR__ . '/' . $this->view);

        $categories = $this->mailTypeCategoriesRepository->all();
        if ($mailTypeCategoryCodes) {
            $categories = array_filter(
                $categories,
                fn($c) => in_array($c->code, $mailTypeCategoryCodes, true)
            );
        }

        $this->template->categories = $categories;

        if (empty($categories)) {
            $this->template->showUnsubscribeAll = false;
            $this->template->showSubscribeAll = false;
            $this->template->render();
            return;
        }

        if ($mailTypeCategoryCodes) {
            $mailTypes = $this->mailTypesRepository->getAllByCategoryCode($mailTypeCategoryCodes, true, true);
        } else {
            $mailTypes = $this->mailTypesRepository->all(true, true);
        }

        $userSubscriptions = [];
        if ($this->presenter->getUser()->isLoggedIn()) {
            $userSubscriptions = $this->mailUserSubscriptionsRepository->userPreferences(
                $this->presenter->getUser()->id,
                true
            );
            $this->template->notLogged = false;
        } else {
            $this->template->notLogged = true;
        }


        $mailTypesByCategories = [];
        foreach ($mailTypes as $mailType) {
            if (!isset($mailTypesByCategories[$mailType->mail_type_category_id])) {
                $mailTypesByCategories[$mailType->mail_type_category_id] = [];
            }
            $mailType->is_subscribed = isset($userSubscriptions[$mailType->id]);

            $variants = $mailType->variants;
            $mailType->variants = [];

            foreach ($variants as $variantId => $variant) {
                $mailType->variants[$variantId] = (object) [
                    'id' => $variantId,
                    'title' => $variant->title,
                    'is_subscribed' => isset($userSubscriptions[$mailType->id]['variants'][$variantId]),
                    'is_default' => (int) $variantId === (int) $mailType->default_variant_id,
                ];
            }
            $mailTypesByCategories[$mailType->mail_type_category_id][] = $mailType;
        }

        $this->template->mailTypesByCategories = $mailTypesByCategories;
        $this->template->mailTypeCategoryCodes = $mailTypeCategoryCodes;
        $this->template->rtmParams = $this->presenter->rtmParams();

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

        $this->presenter->flashMessage($this->translator->translate('remp_mailer.frontend.mail_settings.subscribe_success'));
        $this->presenter->redirect('this');
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

        $this->presenter->flashMessage($this->translator->translate('remp_mailer.frontend.mail_settings.unsubscribe_success'));
        $this->presenter->redirect('this');
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
