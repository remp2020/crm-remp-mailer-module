<?php

namespace Crm\RempMailerModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\RempMailerModule\Components\MailSettings\MailSettingsControlFactoryInterface;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;
use Crm\UsersModule\Auth\AutoLogin\AutoLogin;
use Crm\UsersModule\Auth\UserManager;

class MailSettingsPresenter extends FrontendPresenter
{
    /** @var MailUserSubscriptionsRepository @inject */
    public $mailUserSubscriptionsRepository;

    /** @var MailSettingsControlFactoryInterface @inject */
    public $mailSettingsControlFactory;

    /** @var MailTypesRepository @inject */
    public $mailTypesRepository;

    /** @var AutoLogin @inject */
    public $autoLogin;

    /** @var UserManager @inject */
    public $userManager;

    public function createComponentMailSettings()
    {
        return $this->mailSettingsControlFactory->create();
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

        $this->template->mailType = $mailType;
        $this->mailUserSubscriptionsRepository->subscribeUser($this->getUser()->getIdentity(), $mailType->id, null, $this->utmParams());

        $this->redirect('MailSettings:subscribeEmailSuccess', ['id' => $id, 'medium' => $this->getParameter('utm_medium')]);
    }

    public function renderSubscribeEmailSuccess($id)
    {
        $this->onlyLoggedIn();

        $mailType = $this->mailTypesRepository->getByCode($id);

        if (!$mailType) {
            $this->redirect('mailSettings');
        }

        $this->template->mailType = $mailType;
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

        $userToUnsubscribe = $this->getUser()->getIdentity();
        $token = false;
        if (isset($this->params['token'])) {
            $token = $this->autoLogin->getValidToken($this->params['token']);
        } elseif (isset($this->params['login_t'])) {
            $token = $this->autoLogin->getValidToken($this->params['login_t']);
        }

        // unsubscribing other user than actually logged in
        if ($token && $userToUnsubscribe->email != $token->email) {
            $userToUnsubscribe = $this->userManager->loadUserByEmail($token->email);
            if (!$userToUnsubscribe) {
                $this->template->header = $this->translator->translate('remp_mailer.frontend.mail_unsubscribe.header_no_account', ['email' => $token->email]);
                return;
            }
            $message = $this->translator->translate('remp_mailer.frontend.mail_unsubscribe.header_alt', ['email' => $userToUnsubscribe->email]);
            $this->autoLogin->incrementTokenUse($token);
        }

        if (!$this->mailUserSubscriptionsRepository->isUserUnsubscribed($userToUnsubscribe, $mailType->id)) {
            if ($variantId) {
                $this->mailUserSubscriptionsRepository->unSubscribeUserVariant($userToUnsubscribe, $mailType->id, $variantId, $this->utmParams());
            } else {
                $this->mailUserSubscriptionsRepository->unSubscribeUser($userToUnsubscribe, $mailType->id, $this->utmParams());
            }
        }
        $this->template->header = $message;
    }

    public function utmParams(): array
    {
        return parent::utmParams();
    }
}
