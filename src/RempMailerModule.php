<?php

namespace Crm\RempMailerModule;

use Crm\ApiModule\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Router\ApiIdentifier;
use Crm\ApiModule\Router\ApiRoute;
use Crm\ApplicationModule\AssetsManager;
use Crm\ApplicationModule\Authenticator\AuthenticatorManagerInterface;
use Crm\ApplicationModule\Core;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Menu\MenuItem;
use Crm\ApplicationModule\User\UserDataRegistrator;
use Crm\ApplicationModule\Widget\WidgetManagerInterface;
use Crm\RempMailerModule\Components\MailLogs\MailLogs;
use Crm\RempMailerModule\Components\UserEmailSettings\UserEmailSettingsWidget;
use League\Event\Emitter;
use Tomaj\Hermes\Dispatcher;

class RempMailerModule extends CrmModule
{
    public function registerUserData(UserDataRegistrator $dataRegistrator)
    {
        $dataRegistrator->addUserDataProvider($this->getInstance(\Crm\RempMailerModule\Models\User\RempMailerUserDataProvider::class));
    }

    public function registerEventHandlers(Emitter $emitter)
    {
        $emitter->addListener(
            \Crm\UsersModule\Events\UserRegisteredEvent::class,
            $this->getInstance(\Crm\RempMailerModule\Events\SendWelcomeEmailHandler::class)
        );

        // generic notifications
        $emitter->addListener(
            \Crm\UsersModule\Events\NotificationEvent::class,
            $this->getInstance(\Crm\RempMailerModule\Events\NotificationHandler::class)
        );

        $emitter->addListener(
            \Crm\RempMailerModule\Events\UserMailSubscriptionsChanged::class,
            $this->getInstance(\Crm\RempMailerModule\Events\UserMailSubscriptionsChangedHandler::class)
        );

        $emitter->addListener(
            \Crm\RempMailerModule\Events\ChangeUserNewsletterSubscriptionsEvent::class,
            $this->getInstance(\Crm\RempMailerModule\Events\ChangeUserNewsletterSubscriptionsEventHandler::class)
        );
    }

    public function registerFrontendMenuItems(MenuContainerInterface $menuContainer)
    {
        $menuItem = new MenuItem($this->translator->translate('remp_mailer.menu.email_settings'), ':RempMailer:MailSettings:MailSettings', '', 140);
        $menuContainer->attachMenuItem($menuItem);
    }

    public function registerHermesHandlers(Dispatcher $dispatcher)
    {
        $dispatcher->registerHandler(
            'user-registered',
            $this->getInstance(\Crm\RempMailerModule\Hermes\UserRegisteredHandler::class)
        );
        $dispatcher->registerHandler(
            'email-changed',
            $this->getInstance(\Crm\RempMailerModule\Hermes\EmailChangedHandler::class)
        );
        $dispatcher->registerHandler(
            'mailer-send-email',
            $this->getInstance(\Crm\RempMailerModule\Hermes\SendEmailHandler::class)
        );
    }

    public function registerAdminMenuItems(MenuContainerInterface $menuContainer)
    {
        $mainMenu = new MenuItem($this->translator->translate('remp_mailer.menu.main'), '#', 'fa fa-rocket', 749, false);

        $menuItem = new MenuItem($this->translator->translate('remp_mailer.menu.mailer'), Core::env('REMP_MAILER_HOST'), 'fa fa-envelope', 2000, false);
        $menuContainer->attachMenuItemToForeignModule('#remp', $mainMenu, $menuItem);
    }

    public function registerAuthenticators(AuthenticatorManagerInterface $authenticatorManager)
    {
        $authenticatorManager->registerAuthenticator(
            $this->getInstance(\Crm\RempMailerModule\Models\Authenticator\TokenAuthenticator::class),
            300
        );
    }

    public function registerWidgets(WidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'admin.user.detail.mainbox',
            $this->getInstance(UserEmailSettingsWidget::class),
            200
        );
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            $this->getInstance(MailLogs::class),
            700
        );
    }

    public function registerAssets(AssetsManager $assetsManager)
    {
        $assetsManager->copyAssets(__DIR__ . '/assets', 'layouts/mailer');
    }

    public function registerApiCalls(ApiRoutersContainerInterface $apiRoutersContainer)
    {
        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'mail-template', 'list'),
                \Crm\RempMailerModule\Api\MailTemplateListApiHandler::class,
                \Crm\ApiModule\Authorization\BearerTokenAuthorization::class
            )
        );
    }
}
