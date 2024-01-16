<?php

namespace Crm\RempMailerModule;

use Crm\ApiModule\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Authorization\BearerTokenAuthorization;
use Crm\ApiModule\Router\ApiIdentifier;
use Crm\ApiModule\Router\ApiRoute;
use Crm\ApplicationModule\AssetsManager;
use Crm\ApplicationModule\Authenticator\AuthenticatorManagerInterface;
use Crm\ApplicationModule\Core;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Event\LazyEventEmitter;
use Crm\ApplicationModule\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Menu\MenuItem;
use Crm\ApplicationModule\SeederManager;
use Crm\ApplicationModule\User\UserDataRegistrator;
use Crm\ApplicationModule\Widget\LazyWidgetManagerInterface;
use Crm\RempMailerModule\Api\EmailSubscriptionApiHandler;
use Crm\RempMailerModule\Api\MailTemplateListApiHandler;
use Crm\RempMailerModule\Components\MailLogs\MailLogs;
use Crm\RempMailerModule\Components\UserEmailSettings\UserEmailSettingsWidget;
use Crm\RempMailerModule\Events\ChangeUserNewsletterSubscriptionsEvent;
use Crm\RempMailerModule\Events\ChangeUserNewsletterSubscriptionsEventHandler;
use Crm\RempMailerModule\Events\NotificationHandler;
use Crm\RempMailerModule\Events\SendWelcomeEmailHandler;
use Crm\RempMailerModule\Events\UserMailSubscriptionsChanged;
use Crm\RempMailerModule\Events\UserMailSubscriptionsChangedHandler;
use Crm\RempMailerModule\Hermes\EmailChangedHandler;
use Crm\RempMailerModule\Hermes\SendEmailHandler;
use Crm\RempMailerModule\Hermes\UserRegisteredHandler;
use Crm\RempMailerModule\Models\Authenticator\TokenAuthenticator;
use Crm\RempMailerModule\Models\User\RempMailerUserDataProvider;
use Crm\RempMailerModule\Seeders\SegmentsSeeder;
use Crm\UsersModule\Auth\UserTokenAuthorization;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Events\UserRegisteredEvent;
use Tomaj\Hermes\Dispatcher;

class RempMailerModule extends CrmModule
{
    public function registerUserData(UserDataRegistrator $dataRegistrator)
    {
        $dataRegistrator->addUserDataProvider($this->getInstance(RempMailerUserDataProvider::class));
    }

    public function registerLazyEventHandlers(LazyEventEmitter $emitter)
    {
        $emitter->addListener(
            UserRegisteredEvent::class,
            SendWelcomeEmailHandler::class
        );

        // generic notifications
        $emitter->addListener(
            NotificationEvent::class,
            NotificationHandler::class
        );

        $emitter->addListener(
            UserMailSubscriptionsChanged::class,
            UserMailSubscriptionsChangedHandler::class
        );

        $emitter->addListener(
            ChangeUserNewsletterSubscriptionsEvent::class,
            ChangeUserNewsletterSubscriptionsEventHandler::class
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
            $this->getInstance(UserRegisteredHandler::class)
        );
        $dispatcher->registerHandler(
            'email-changed',
            $this->getInstance(EmailChangedHandler::class)
        );
        $dispatcher->registerHandler(
            'mailer-send-email',
            $this->getInstance(SendEmailHandler::class)
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
            $this->getInstance(TokenAuthenticator::class),
            300
        );
    }

    public function registerLazyWidgets(LazyWidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'admin.user.detail.mainbox',
            UserEmailSettingsWidget::class,
            200
        );
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            MailLogs::class,
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
                MailTemplateListApiHandler::class,
                BearerTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'mailer', 'unsubscribe'),
                EmailSubscriptionApiHandler::class,
                UserTokenAuthorization::class
            )
        );
        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'mailer', 'subscribe'),
                EmailSubscriptionApiHandler::class,
                UserTokenAuthorization::class
            )
        );
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(SegmentsSeeder::class));
    }
}
