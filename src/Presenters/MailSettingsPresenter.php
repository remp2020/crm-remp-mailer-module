<?php

namespace Crm\RempMailerModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\RempMailerModule\Components\MailSettings\MailSettingsControlFactoryInterface;
use Crm\RempMailerModule\Models\Api\Client;
use Crm\RempMailerModule\Models\Api\MailSubscribeRequest;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\DI\Attributes\Inject;

class MailSettingsPresenter extends FrontendPresenter
{
    #[Inject]
    public UserManager $userManager;

    public function __construct(
        protected MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
        protected MailTypesRepository $mailTypesRepository,
        protected Client $mailerApiClient,
    ) {
        parent::__construct();
    }

    public function createComponentMailSettings(MailSettingsControlFactoryInterface $mailSettingsControlFactory)
    {
        return $mailSettingsControlFactory->create();
    }

    public function renderMailSettings()
    {
        $this->onlyLoggedIn();
    }

    public function renderSubscribeEmail($id)
    {
        $this->onlyLoggedIn();

        $mailType = $this->mailTypesRepository->getByCode($id);
        if (!$mailType) {
            $this->redirect('mailSettings');
        }
        $user = $this->userManager->loadUser($this->getUser());

        $this->template->mailType = $mailType;
        $msr = (new MailSubscribeRequest)
            ->setMailTypeCode($mailType->code)
            ->setMailTypeId($mailType->id)
            ->setUser($user);
        $this->mailUserSubscriptionsRepository->subscribe($msr, $this->rtmParams());

        $this->redirect('MailSettings:subscribeEmailSuccess', [
            'id' => $id,
            'medium' => $this->getParameter('rtm_medium') ?? $this->getParameter('utm_medium') ?? null,
        ]);
    }

    public function renderSubscribeEmailSuccess($id, array $mailTypeCategoryCodes = null)
    {
        $this->onlyLoggedIn();

        $mailType = $this->mailTypesRepository->getByCode($id);

        if (!$mailType) {
            $this->redirect('mailSettings');
        }

        $this->template->mailType = $mailType;
        $this->template->mailTypeCategoryCodes = $mailTypeCategoryCodes;
        $this->template->medium = $this->getParameter('medium');
    }

    public function renderUnSubscribeEmail($id, $variantId = null)
    {
        $this->onlyLoggedIn();

        $mailType = $this->mailTypesRepository->getByCode($id);
        if (!$mailType) {
            $this->redirect('mailSettings');
        }
        if ($mailType->locked) {
            $this->redirect('mailSettings');
        }

        $message = $this->translator->translate('remp_mailer.frontend.mail_unsubscribe.header');

        $userToUnsubscribe = $this->userManager->loadUser($this->getUser());

        $email = null;
        if (isset($this->params['token'])) {
            $email = $this->mailerApiClient->checkAutologinToken($this->params['token']);
        } elseif (isset($this->params['login_t'])) {
            $email = $this->mailerApiClient->checkAutologinToken($this->params['login_t']);
        }

        // unsubscribing other user than actually logged in
        if ($email && $userToUnsubscribe->email !== $email) {
            $userToUnsubscribe = $this->userManager->loadUserByEmail($email);
            if (!$userToUnsubscribe) {
                $this->template->header = $this->translator->translate('remp_mailer.frontend.mail_unsubscribe.header_no_account', ['email' => $email]);
                return;
            }
            $message = $this->translator->translate('remp_mailer.frontend.mail_unsubscribe.header_alt', ['email' => $userToUnsubscribe->email]);
        }

        if ($this->mailUserSubscriptionsRepository->isUserSubscribed($userToUnsubscribe, $mailType->id)) {
            $msr = (new MailSubscribeRequest)
                ->setMailTypeCode($mailType->code)
                ->setMailTypeId($mailType->id)
                ->setUser($userToUnsubscribe);

            if ($variantId) {
                $msr->setVariantId($variantId);
            }

            $this->mailUserSubscriptionsRepository->unsubscribe($msr, $this->rtmParams());
        }
        $this->template->header = $message;
    }
}
