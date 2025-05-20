<?php

namespace Crm\RempMailerModule\Components\MailSettings;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\RempMailerModule\Models\Api\MailSubscribeRequest;
use Crm\RempMailerModule\Models\MailerConfig;
use Crm\RempMailerModule\Repositories\MailTypeCategoriesRepository;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UserEmailConfirmationsRepository;
use League\Event\Emitter;
use Nette\Application\UI\Control;
use Nette\Localization\Translator;

/**
 * @property FrontendPresenter $presenter
 */
class MailSettings extends Control
{
    private string $view = 'mail_settings.latte';

    public function __construct(
        private MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
        private MailTypeCategoriesRepository $mailTypeCategoriesRepository,
        private MailTypesRepository $mailTypesRepository,
        private UserManager $userManager,
        private Translator $translator,
        private MailerConfig $mailerConfig,
        private Emitter $emitter,
        private UserEmailConfirmationsRepository $userEmailConfirmationsRepository,
    ) {
    }

    public function render(array $mailTypeCategoryCodes = null)
    {
        $this->template->setFile(__DIR__ . '/' . $this->view);

        $categories = $this->mailTypeCategoriesRepository->all();
        if ($mailTypeCategoryCodes) {
            $categories = array_filter(
                $categories,
                fn($c) => in_array($c->code, $mailTypeCategoryCodes, true),
            );
        }

        $this->template->categories = $categories;
        $this->template->rtmParams = $this->presenter->rtmParams();
        $this->template->prohibitedMode = $this->isProhibited();

        if (empty($categories)) {
            $this->template->showUnsubscribeAll = false;
            $this->template->showSubscribeAll = false;
            $this->template->mailTypesByCategories = [];
            $this->template->mailTypeCategoryCodes = [];
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
                true,
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
        $this->template->prohibitedMode = $this->isProhibited();

        $this->template->render();
    }

    public function handleAllSubscribe(array $cat = null)
    {
        $this->presenter->onlyLoggedIn();
        $user = $this->userManager->loadUser($this->presenter->user);
        $this->onlyConfirmedUser();

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

    public function handleConfirmEmail()
    {
        $this->presenter->onlyLoggedIn();
        $user = $this->userManager->loadUser($this->presenter->getUser());

        $confirmationUrl = $this->presenter->link('//:Users:Users:EmailConfirm', [
            'token' => $this->userEmailConfirmationsRepository->generate($user->id)['token'],
        ]);

        $this->emitter->emit(new NotificationEvent(
            $this->emitter,
            $user,
            'email-confirmation',
            [
                'confirmation_url' => $confirmationUrl,
            ],
        ));

        $this->presenter->flashMessage($this->translator->translate('remp_mailer.frontend.mail_settings.confirmation_email_sent', ['email' => $user->email]));
        $this->redirect('this');
    }

    private function onlyConfirmedUser(): void
    {
        if ($this->isProhibited()) {
            $this->presenter->flashMessage($this->translator->translate('remp_mailer.frontend.mail_settings.subscribe_not_allowed'), 'warning');
            $this->presenter->redirect('this');
        }
    }

    private function isProhibited(): bool
    {
        if (!$this->mailerConfig->getSubscribeOnlyConfirmedUser()) {
            return false;
        }

        $user = $this->userManager->loadUser($this->presenter->getUser());
        return $user && $user->confirmed_at === null;
    }
}
