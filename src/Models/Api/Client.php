<?php

namespace Crm\RempMailerModule\Models\Api;

use Crm\RempMailerModule\DI\Config;
use Crm\RempMailerModule\Models\MailerException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Nette\Http\IResponse;
use Nette\Utils\Json;
use Tracy\Debugger;
use Tracy\ILogger;

class Client
{
    private const CHECK_AUTOLOGIN_TOKEN = '/api/v1/users/check-token';

    private const EMAIL_CHANGED = '/api/v1/users/email-changed';

    private const USER_REGISTERED = '/api/v1/users/user-registered';

    private const SEND_EMAIL = '/api/v1/mailers/send-email';

    private const LOGS = '/api/v1/users/logs';

    private const LOGS_COUNT = '/api/v1/users/logs-count-per-status';

    private const ALL_MAIL_CATEGORIES = '/api/v1/mailers/mail-type-categories';

    private const MAIL_TYPES = '/api/v1/mailers/mail-types';

    private const MAIL_TEMPLATES = '/api/v1/mailers/templates';

    private const SUBSCRIBE = '/api/v1/users/subscribe';

    private const UNSUBSCRIBE = '/api/v1/users/un-subscribe';

    private const BULK_SUBSCRIBE = '/api/v1/users/bulk-subscribe';

    private const IS_USER_UNSUBSCRIBED = '/api/v1/users/is-unsubscribed';

    private const UNSUBSCRIBE_VARIANT = '/api/v1/users/un-subscribe-variant';

    private const USER_PREFERENCES = '/api/v1/users/user-preferences';

    private $apiClient;

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

    /**
     * @return bool|string
     */
    public function checkAutologinToken($token)
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
                Debugger::log($e);
            }
            return false;
        } catch (\Exception $e) {
            Debugger::log($e);
            return false;
        }
    }

    public function emailChanged(string $originalEmail, string $newEmail)
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
            Debugger::log($e->getMessage());
            return false;
        }
    }

    public function userRegistered(string $email, string $userId)
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
            Debugger::log($e->getMessage());
            return false;
        }
    }

    public function sendEmail(string $email, string $templateCode, array $params = [], string $context = null, array $attachments = [], $scheduleAt = null)
    {
        try {
            $json = array_filter([
                'email' => $email,
                'mail_template_code' => $templateCode,
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
            Debugger::log($e->getResponse()->getBody()->getContents());
            throw new MailerException($e->getMessage());
        }
    }

    public function getMailLogs(?string $email, ?string $filter, ?int $limit, ?int $page, ?array $mailTemplateIds)
    {
        try {
            $data = [];

            if ($filter !== null) {
                $data['filter'] = [$filter];
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
            if ($mailTemplateIds !== null) {
                $data['mail_template_ids'] = $mailTemplateIds;
            }

            $logs = $this->apiClient->post(self::LOGS, [
                RequestOptions::JSON => $data,
            ]);

            $records = Json::decode($logs->getBody());
            foreach ($records as $i => $row) {
                $records[$i]->sent_at = new \DateTime($row->sent_at);
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

            return Json::decode($logsCount->getBody()->getContents(), true);
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

    public function getMailTypes(?string $code = null, ?int $publicListing = null): ?array
    {
        try {
            $data = [];

            if ($publicListing !== null) {
                $data['public_listing'] = 1;
            }
            if ($code !== null) {
                $data['code'] = $code;
            }

            $types = $this->apiClient->get(self::MAIL_TYPES, [
                RequestOptions::QUERY => $data,
            ]);

            return Json::decode($types->getBody())->data;
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e, ILogger::ERROR);
            return null;
        }
    }

    public function subscribeUser(int $userId, string $email, int $mailTypeId, ?int $variantId = null, array $rtmParams = [])
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

    public function unSubscribeUser(int $userId, string $email, int $mailTypeId, array $rtmParams = [])
    {
        try {
            $data =  [
                'email' => $email,
                'user_id' => $userId,
                'list_id' => $mailTypeId,
            ];
            if (!empty($rtmParams)) {
                $data['rtm_params'] = $rtmParams;

                // transition-period (UTM -> RTM), will be removed
                $utmParams = [];
                foreach ($rtmParams as $paramName => $value) {
                    $utmParams['utm_' . substr($paramName, 4)] = $value;
                }
                $data['utm_params'] = $utmParams;
            }
            $this->apiClient->post(self::UNSUBSCRIBE, ['json' =>
                $data
            ]);
            return true;
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e->getMessage());
            throw new MailerException($e->getMessage());
        }
    }

    public function isUserUnsubscribed(int $userId, string $email, int $mailTypeId)
    {
        try {
            $result = $this->apiClient->post(self::IS_USER_UNSUBSCRIBED, ['json' =>
                [
                    'user_id' => $userId,
                    'email' => $email,
                    'list_id' => $mailTypeId,
                ]
            ]);

            return Json::decode($result->getBody());
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e, ILogger::ERROR);
            throw new MailerException($e->getMessage());
        }
    }

    public function unSubscribeUserVariant(int $userId, string $email, int $mailTypeId, int $variantId, array $rtmParams = [])
    {
        try {
            $this->apiClient->post(self::UNSUBSCRIBE_VARIANT, ['json' =>
                [
                    'user_id' => $userId,
                    'user_email' => $email,
                    'variant_id' => $variantId,
                    'list_id' => $mailTypeId,
                    'rtm_params' => $rtmParams
                ]
            ]);
            return true;
        } catch (ServerException | ConnectException $e) {
            Debugger::log($e, ILogger::ERROR);
            throw new MailerException($e->getMessage());
        }
    }

    public function getUserPreferences($userId, $email, ?bool $subscribed = null)
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
    public function bulkSubscribe(array $subscribeRequests)
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
}
