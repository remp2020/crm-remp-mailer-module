<?php

namespace Crm\RempMailerModule\Models\Api;

use Crm\RempMailerModule\DI\Config;
use Crm\RempMailerModule\Models\MailerException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Nette\Http\IResponse;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tracy\Debugger;
use Tracy\ILogger;

class Client
{
    private const CHECK_AUTOLOGIN_TOKEN = '/api/v1/users/check-token';
    private const EMAIL_CHANGED = '/api/v1/users/email-changed';
    private const USER_REGISTERED = '/api/v1/users/user-registered';
    private const USER_DELETE = '/api/v1/users/delete';
    private const SEND_EMAIL = '/api/v1/mailers/send-email';
    private const LOGS = '/api/v1/users/logs';
    private const LOGS_COUNT = '/api/v1/users/logs-count-per-status';
    private const ALL_MAIL_CATEGORIES = '/api/v1/mailers/mail-type-categories';
    private const MAIL_TYPES = '/api/v2/mailers/mail-types';
    private const MAIL_TYPES_V3 = '/api/v3/mailers/mail-types';
    private const MAIL_TEMPLATES = '/api/v1/mailers/templates';
    private const SUBSCRIBE = '/api/v1/users/subscribe';
    private const UNSUBSCRIBE = '/api/v1/users/un-subscribe';
    private const BULK_SUBSCRIBE = '/api/v1/users/bulk-subscribe';
    private const IS_USER_UNSUBSCRIBED = '/api/v1/users/is-unsubscribed';
    private const IS_USER_SUBSCRIBED = '/api/v1/users/is-subscribed';
    private const USER_PREFERENCES = '/api/v1/users/user-preferences';
    private const GENERATE_MAIL = '/api/v1/mailers/generate-mail';
    private const JOBS = '/api/v2/mailers/jobs';

    private \GuzzleHttp\Client $apiClient;

    public function __construct(Config $config)
    {
        $this->apiClient = new \GuzzleHttp\Client([
            'base_uri' => $config->getHost(),
            'headers' => [
                'Authorization' => 'Bearer ' . $config->getApiToken(),
                'Accept' => 'application/json',
            ]
        ]);
    }

    public function checkAutologinToken($token): bool|string
    {
        try {
            $result = $this->apiClient->post(self::CHECK_AUTOLOGIN_TOKEN, [
                'json' => [
                    'token' => $token,
                ],
            ]);

            $data = json_decode($result->getBody(), true);
            if (isset($data['status']) && $data['status'] === 'ok' && isset($data['email'])) {
                return $data['email'];
            }
            return false;
        } catch (ClientException $e) {
            if (!in_array($e->getResponse()->getStatusCode(), [
                IResponse::S403_FORBIDDEN,
                IResponse::S404_NOT_FOUND,
            ], true)) {
                Debugger::log($e, Debugger::ERROR);
            }
            return false;
        } catch (\Exception $e) {
            Debugger::log($e, Debugger::ERROR);
            return false;
        }
    }

    public function emailChanged(string $originalEmail, string $newEmail): bool
    {
        try {
            $this->apiClient->post(self::EMAIL_CHANGED, [
                'form_params' => [
                    'original_email' => $originalEmail,
                    'new_email' => $newEmail
                ]
            ]);

            return true;
        } catch (\Exception $e) {
            Debugger::log($e->getMessage(), Debugger::ERROR);
            return false;
        }
    }

    public function userRegistered(string $email, string $userId): bool
    {
        try {
            $this->apiClient->post(self::USER_REGISTERED, [
                'form_params' => [
                    'email' => $email,
                    'user_id' => $userId,
                ]
            ]);

            return true;
        } catch (\Exception $e) {
            Debugger::log($e->getMessage(), Debugger::ERROR);
            return false;
        }
    }

    public function userDelete($email): bool
    {
        try {
            $this->apiClient->post(self::USER_DELETE, [
                'json' => [
                    'email' => $email,
                ],
            ]);

            return true;
        } catch (ClientException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === IResponse::S404_NOT_FOUND) {
                $response = Json::decode($e->getResponse()->getBody(), Json::FORCE_ARRAY);
                if ($response['code'] === 'user_not_found') {
                    // user had zero emails sent from Mailer or was removed in past
                    return true;
                }
            }

            Debugger::log($e->getMessage(), Debugger::ERROR);
            return false;
        } catch (\Exception $e) {
            Debugger::log($e->getMessage(), Debugger::ERROR);
            return false;
        }
    }

    public function sendEmail(string $email, string $templateCode, array $params = [], string $context = null, array $attachments = [], $scheduleAt = null, string $locale = null): bool
    {
        try {
            $json = array_filter([
                'email' => $email,
                'mail_template_code' => $templateCode,
                'locale' => $locale,
                'params' => count($params) ? $params : null, // don't send empty params at all, it would be encoded as array instead of object
                'context' => $context,
                'attachments' => $attachments,
            ]);
            if ($scheduleAt) {
                $json['schedule_at'] = $scheduleAt;
            }

            $this->apiClient->post(self::SEND_EMAIL, [
                'json' => $json,
            ]);

            return true;
        } catch (ClientException $e) {
            Debugger::log($e->getResponse()->getBody()->getContents(), Debugger::ERROR);
            throw new MailerException($e->getMessage());
        }
    }

    public function getMailLogs(?string $email, ?array $filter, ?int $limit, ?int $page, ?array $mailTemplateIds, ?array $mailTemplateCodes): ?array
    {
        try {
            $data = [];

            if ($filter !== null) {
                foreach ($filter as $filterBy => $filterTimeFrame) {
                    $filter[$filterBy] = array_map(function ($item) {
                        if ($item instanceof DateTime) {
                            $item = $item->format(\DateTimeInterface::RFC3339);
                        }
                        return $item;
                    }, $filterTimeFrame);
                }
                $data['filter'] = $filter;
            }
            if ($limit !== null) {
                $data['limit'] = $limit;
            }
            if ($page !== null) {
                $data['page'] = $page;
            }
            if ($email !== null) {
                $data['email'] = $email;
            }
            if (!empty($mailTemplateIds)) {
                $data['mail_template_ids'] = $mailTemplateIds;
            }
            if (!empty($mailTemplateCodes)) {
                $data['mail_template_codes'] = $mailTemplateCodes;
            }

            $logs = $this->apiClient->post(self::LOGS, [
                RequestOptions::JSON => $data,
            ]);

            $records = Json::decode($logs->getBody());
            foreach ($records as $i => $row) {
                $records[$i]->sent_at = $this->toDateTime($row->sent_at);
                $records[$i]->delivered_at = $this->toDateTime($row->delivered_at);
                $records[$i]->opened_at = $this->toDateTime($row->opened_at);
                $records[$i]->clicked_at = $this->toDateTime($row->clicked_at);
                $records[$i]->spam_complained_at = $this->toDateTime($row->spam_complained_at);
                $records[$i]->hard_bounced_at = $this->toDateTime($row->hard_bounced_at);
            }
            return $records;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() == IResponse::S400_BAD_REQUEST) {
                Debugger::log($e, ILogger::ERROR);
                return null;
            }
            throw $e;
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e, ILogger::ERROR);
            return null;
        }
    }

    public function countMailLogs(string $email, array $statuses, ?\DateTime $from, ?\DateTime $to): ?array
    {
        try {
            $data = [
                'email' => $email,
                'filter' => $statuses,
            ];

            if ($from) {
                $data['from'] = $from->format(DATE_RFC3339);
            }
            if ($to) {
                $data['to'] = $to->format(DATE_RFC3339);
            }

            $logsCount = $this->apiClient->post(self::LOGS_COUNT, [
                RequestOptions::JSON => $data
            ]);

            return Json::decode($logsCount->getBody()->getContents(), Json::FORCE_ARRAY);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() == IResponse::S400_BAD_REQUEST) {
                Debugger::log($e, ILogger::ERROR);
                return null;
            }
            throw $e;
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e, ILogger::ERROR);
            return null;
        }
    }

    public function getAllCategories(): ?array
    {
        try {
            $types = $this->apiClient->get(self::ALL_MAIL_CATEGORIES);
            return Json::decode($types->getBody());
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e, ILogger::ERROR);
            return null;
        }
    }

    public function getMailTypes(
        ?array $codes = null,
        ?array $categoryCodes = null,
        ?int $publicListing = null,
        bool $includeVariantsData = false,
    ): ?array {
        try {
            $data = [];

            if ($publicListing !== null) {
                $data['public_listing'] = 1;
            }
            if ($codes !== null) {
                $data['code'] = $codes;
            }
            if ($categoryCodes !== null) {
                $data['mail_type_category_code'] = $categoryCodes;
            }

            $types = $this->apiClient->get($includeVariantsData ? self::MAIL_TYPES_V3 : self::MAIL_TYPES, [
                RequestOptions::QUERY => $data,
            ]);

            return Json::decode($types->getBody())->data;
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e, ILogger::ERROR);
            return null;
        }
    }

    /**
     * @throws MailerException
     */
    private function mailSubscribeRequest(MailSubscribeRequest $msr, array $rtmParams = []): MailSubscribeResponse
    {
        try {
            $data = $msr->getRequestData();
            unset($data['subscribe']);

            if ($msr->getSubscribed()) {
                $response = $this->apiClient->post(self::SUBSCRIBE, ['json' => $data]);
            } else {
                if (!empty($rtmParams)) {
                    // Mailer supports RTM params tracking only for unsubscribe request
                    $data['rtm_params'] = array_filter([
                        'rtm_source' => $rtmParams['rtm_source'] ?? null,
                        'rtm_medium' => $rtmParams['rtm_medium'] ?? null,
                        'rtm_campaign' => $rtmParams['rtm_campaign'] ?? null,
                        'rtm_content' => $rtmParams['rtm_content'] ?? null,
                    ]);
                }
                $response = $this->apiClient->post(self::UNSUBSCRIBE, ['json' => $data]);
            }
            return MailSubscribeResponse::fromApiResponse(Json::decode($response->getBody()->getContents()));
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e->getMessage(), Debugger::ERROR);
            throw new MailerException($e->getMessage());
        } catch (ClientException $e) {
            $response = Json::decode($e->getResponse()->getBody());
            throw new MailerException($response->message, $e->getResponse()->getStatusCode());
        }
    }

    /**
     * @throws MailerException
     */
    public function subscribe(MailSubscribeRequest $msr, array $rtmParams = []): MailSubscribeResponse
    {
        if (!$msr->getSubscribed()) {
            throw new \Exception('Invalid MailSubscribeRequest provided: calling subscribe() with $msr::subscribed == FALSE.');
        }
        return $this->mailSubscribeRequest($msr, $rtmParams);
    }

    /**
     * @throws MailerException
     */
    public function unsubscribe(MailSubscribeRequest $msr, array $rtmParams = []): ?bool
    {
        if ($msr->getSubscribed()) {
            throw new \Exception('Invalid MailSubscribeRequest provided: calling unsubscribe() with $msr::subscribed == TRUE.');
        }
        $this->mailSubscribeRequest($msr, $rtmParams);
        return true;
    }

    /**
     * @param int $userId
     * @param string $email
     * @param int $mailTypeId
     * @param int|null $variantId
     * @param array $rtmParams
     * @return bool
     * @throws MailerException
     * @deprecated Recommended to use Client::subscribe method instead.
     */
    public function subscribeUser(int $userId, string $email, int $mailTypeId, ?int $variantId = null, array $rtmParams = []): bool
    {
        try {
            $data = [
                'email' => $email,
                'user_id' => $userId,
                'list_id' => $mailTypeId
            ];

            if ($variantId !== null) {
                $data['variant_id'] = $variantId;
            }

            $this->apiClient->post(self::SUBSCRIBE, [
                RequestOptions::JSON => $data
            ]);
            return true;
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e, ILogger::ERROR);
            throw new MailerException($e->getMessage());
        }
    }

    /**
     * @param int $userId
     * @param string $email
     * @param int $mailTypeId
     * @param array $rtmParams
     * @return bool
     * @throws MailerException
     * @deprecated Recommended to use Client::unsubscribe method instead.
     */
    public function unSubscribeUser(int $userId, string $email, int $mailTypeId, array $rtmParams = []): bool
    {
        try {
            $data =  [
                'email' => $email,
                'user_id' => $userId,
                'list_id' => $mailTypeId,
            ];
            if (!empty($rtmParams)) {
                $data['rtm_params'] = $rtmParams;
            }
            $this->apiClient->post(self::UNSUBSCRIBE, ['json' =>
                $data
            ]);
            return true;
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e->getMessage(), Debugger::ERROR);
            throw new MailerException($e->getMessage());
        }
    }

    public function isUserUnsubscribed(int $userId, string $email, int $mailTypeId)
    {
        try {
            $result = $this->apiClient->post(
                self::IS_USER_UNSUBSCRIBED,
                ['json' =>
                array_filter([
                    'user_id' => $userId,
                    'email' => $email,
                    'list_id' => $mailTypeId,
                ])
                ]
            );

            return Json::decode($result->getBody());
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e, ILogger::ERROR);
            throw new MailerException($e->getMessage());
        }
    }

    public function isUserSubscribed(int $userId, string $email, int $mailTypeId, ?int $variantId = null)
    {
        try {
            $result = $this->apiClient->post(
                self::IS_USER_SUBSCRIBED,
                ['json' => array_filter([
                    'user_id' => $userId,
                    'email' => $email,
                    'list_id' => $mailTypeId,
                    'variant_id' => $variantId,
                    ])
                ]
            );

            return Json::decode($result->getBody());
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e, ILogger::ERROR);
            throw new MailerException($e->getMessage());
        }
    }

    public function getUserPreferences($userId, $email, ?bool $subscribed = null): ?array
    {
        $data = [
            'user_id' => $userId,
            'email' => $email
        ];

        if ($subscribed !== null) {
            $data['subscribed'] = $subscribed;
        }

        try {
            $result = $this->apiClient->post(
                self::USER_PREFERENCES,
                [
                    'json' => $data
                ]
            );

            return Json::decode($result->getBody(), Json::FORCE_ARRAY);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() == IResponse::S404_NOT_FOUND) {
                return [];
            }
            if ($e->getResponse()->getStatusCode() == IResponse::S400_BAD_REQUEST) {
                Debugger::log($e, ILogger::ERROR);
                return null;
            }
            throw $e;
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e, ILogger::ERROR);
            return null;
        }
    }

    /**
     * @param MailSubscribeRequest[] $subscribeRequests
     */
    public function bulkSubscribe(array $subscribeRequests): ?array
    {
        try {
            $payload = [
                'users' => [],
            ];
            foreach ($subscribeRequests as $subscribeRequest) {
                $payload['users'][] = $subscribeRequest->getRequestData();
            }
            $result = $this->apiClient->post(
                self::BULK_SUBSCRIBE,
                [
                    'json' => $payload
                ]
            );

            return Json::decode($result->getBody(), Json::FORCE_ARRAY);
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e, ILogger::ERROR);
            return null;
        }
    }

    public function getTemplates(array $mailTypeCodes = [], bool $withMailTypes = false): array
    {
        try {
            $query = [];
            if (count($mailTypeCodes)) {
                $query['mail_type_codes'] = array_values($mailTypeCodes);
            }
            if ($withMailTypes) {
                $query['with_mail_types'] = $withMailTypes;
            }
            $result = $this->apiClient->get(
                self::MAIL_TEMPLATES,
                [
                    RequestOptions::QUERY => $query
                ]
            );

            return Json::decode($result->getBody());
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e, ILogger::ERROR);
            return [];
        }
    }

    public function getTemplate(string $code)
    {
        $query['codes'] = [$code];
        $result = $this->apiClient->get(
            self::MAIL_TEMPLATES,
            [
                    RequestOptions::QUERY => $query
                ]
        );

        $templates = Json::decode($result->getBody());
        return reset($templates);
    }

    public function generateMail(array $params): array
    {
        try {
            $result = $this->apiClient->post(self::GENERATE_MAIL, [
                'form_params' => $params,
            ]);

            return Json::decode((string) $result->getBody(), forceArrays: true);
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e->getMessage(), Debugger::ERROR);
            throw new MailerException($e->getMessage());
        } catch (ClientException $e) {
            $response = Json::decode($e->getResponse()->getBody());
            Debugger::log($response, Debugger::ERROR);
            throw new MailerException($response->message, $e->getResponse()->getStatusCode());
        }
    }

    public function createMailTemplate($htmlContent, $textContent, $name, $code, $description, $layoutCode, $mailTypeCode, $sender, $subject): array
    {
        try {
            $result = $this->apiClient->post(self::MAIL_TEMPLATES, [
                'form_params' => [
                    'name' => $name,
                    'code' => $code,
                    'description' => $description,
                    'mail_layout_code' => $layoutCode,
                    'mail_type_code' => $mailTypeCode,
                    'from' => $sender,
                    'subject' => $subject,
                    'template_html' => $htmlContent,
                    'template_text' => $textContent,
                ],
            ]);
            return Json::decode((string) $result->getBody(), forceArrays: true);
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e->getMessage(), Debugger::ERROR);
            throw new MailerException($e->getMessage());
        } catch (ClientException $e) {
            $response = Json::decode($e->getResponse()->getBody());
            Debugger::log($response, Debugger::ERROR);
            throw new MailerException($response->message, $e->getResponse()->getStatusCode());
        }
    }

    public function createMailJob($includeSegments, $templateCode, $mailTypeVariantCode = null, $context = null, $excludeSegments = null): array
    {
        try {
            $result = $this->apiClient->post(self::JOBS, [
                'body' => Json::encode(array_filter([
                    'include_segments' => $includeSegments,
                    'template_code' => $templateCode,
                    'mail_type_variant_code' => $mailTypeVariantCode,
                    'context' => $context,
                    'exclude_segments' => $excludeSegments,
                ])),
            ]);
            return Json::decode((string) $result->getBody(), forceArrays: true);
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e->getMessage(), Debugger::ERROR);
            throw new MailerException($e->getMessage());
        } catch (ClientException $e) {
            $response = Json::decode($e->getResponse()->getBody());
            Debugger::log($response, Debugger::ERROR);
            throw new MailerException($response->message, $e->getResponse()->getStatusCode());
        }
    }

    private function toDateTime(?string $dateTime): ?\DateTime
    {
        if ($dateTime === null) {
            return null;
        }

        return new \DateTime($dateTime);
    }
}
