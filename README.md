# CRM REMP Mailer Module

REMP Mailer Module is an integration module connecting CRM with [REMP Mailer](https://github.com/remp2020/remp/tree/master/Mailer).

Module adds listener for `NotificationEvent` to send emails, and attaches widgets to allow users/admins to configure newsletter subscriptions and to see logs of all sent emails in administration.

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

When everything is ready, make sure you update CRM internal configuration by running following commands (they should always be part of your release process):

```
php bin/command.php api:generate_access
php bin/command.php application:seed
```

## API documentation

All examples use `http://crm.press` as a base domain. Please change the host to the one you use
before executing the examples.

All examples use `XXX` as a default value for authorization token, please replace it with the
real tokens:

* *API tokens.* Standard API keys for server-server communication. It identifies the calling application as a whole.
  They can be generated in CRM Admin (`/api/api-tokens-admin/`) and each API key has to be whitelisted to access
  specific API endpoints. By default the API key has access to no endpoint.
* *User tokens.* Generated for each user during the login process, token identify single user when communicating between
  different parts of the system. The token can be read:
    * From `n_token` cookie if the user was logged in via CRM.
    * From the response of [`/api/v1/users/login` endpoint](https://github.com/remp2020/crm-users-module#post-apiv1userslogin) -
      you're free to store the response into your own cookie/local storage/session.

API responses can contain following HTTP codes:

| Value | Description |
| --- | --- |
| 200 OK | Successful response, default value | 
| 400 Bad Request | Invalid request (missing required parameters) | 
| 403 Forbidden | The authorization failed (provided token was not valid) | 
| 404 Not found | Referenced resource wasn't found | 

If possible, the response includes `application/json` encoded payload with message explaining
the error further.

---

#### POST `/api/v1/mailer/subscribe`

API endpoint calls REMP Mailer api endpoint and subscribes user to given mail type and variant.

##### *Headers:*

| Name | Value | Required | Description |
| --- |---| --- | --- |
| Authorization | Bearer *String* | yes | User token. |

##### *Example:*
```shell
curl --location --request POST 'http://crm.press/api/v1/mailer/subscribe' \
--header 'Authorization: Bearer XXX' \
--form 'mail_type_code="alerts"' \
--form 'variant_code="daily"'
```

Response:

```json5
{
    "status": "ok"
}
```

--- 

#### POST `/api/v1/mailer/unsubscribe`

API endpoint calls REMP Mailer api endpoint and unsubscribes user to given mail type and variant.

##### *Headers:*

| Name | Value | Required | Description |
| --- |---| --- | --- |
| Authorization | Bearer *String* | yes | User token. |

##### *Example:*
```shell
curl --location --request POST 'http://crm.press/api/v1/mailer/unsubscribe' \
--header 'Authorization: Bearer XXX' \
--form 'mail_type_code="alerts"' \
--form 'variant_code="daily"'
```

Response:

```json5
{
    "status": "ok"
}
```

