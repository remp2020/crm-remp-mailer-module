# CRM REMP Mailer Module

## Installing module

We recommend using Composer for installation and update management.

```shell
composer require remp/crm-remp-mailer-module
```

## Enabling and configuring module

Add installed extension to your `app/config/config.neon` file.

```neon
extensions:
	remp_mailer: Crm\RempMailerModule\DI\RempMailerModuleExtension
```

When added, you need to provide URL of REMP Mailer instance and API token that CRM should use to communicate with Mailer.

Amend your configuration file and replace the example values with real ones:

```neon
remp_mailer:
    # Base URL where mailer is hosted.
    host: http://mailer.remp.press

    # API token to communicate with Mailer. By default the token can be acquired in REMP SSO.
    api_token: abcdef123456789
```

If the configuration is incomplete, initialization will log an error to your configured logger and won't enable the module in CRM.