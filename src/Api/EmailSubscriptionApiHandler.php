<?php

namespace Crm\RempMailerModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\RempMailerModule\Models\Api\MailSubscribeRequest;
use Crm\RempMailerModule\Models\MailerException;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;
use Crm\UsersModule\Models\Auth\UsersApiAuthorizationInterface;
use Nette\Http\Request;
use Nette\Http\Response;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class EmailSubscriptionApiHandler extends ApiHandler
{
    public function __construct(
        private Request $request,
        private MailTypesRepository $mailTypesRepository,
        private MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new PostInputParam('mail_type_code'))->setRequired(),
            (new PostInputParam('variant_code')),
            (new PostInputParam('variant_id')),
            // tracker data
            (new PostInputParam('rtm_source')),
            (new PostInputParam('rtm_medium')),
            (new PostInputParam('rtm_campaign')),
            (new PostInputParam('rtm_content')),
            (new PostInputParam('rtm_variant')),
        ];
    }

    /**
     * @throws \Exception
     */
    public function handle(array $params): ResponseInterface
    {
        $authorization = $this->getAuthorization();
        if (!$authorization instanceof UsersApiAuthorizationInterface) {
            throw new \Exception('Invalid authorization configured for EmailSubscriptionApiHandler');
        }

        $authorizedUsers = $authorization->getAuthorizedUsers();
        if (count($authorizedUsers) > 1) {
            $response = new JsonApiResponse(Response::S401_UNAUTHORIZED, [
                'status' => 'error',
                'code' => 'unauthorized',
                'message' => 'Unable to authorize specific user.',
            ]);
            return $response;
        }
        $user = reset($authorizedUsers);

        $mailType = $this->mailTypesRepository->getByCode($params['mail_type_code']);
        if (!$mailType) {
            $result = [
                'status' => 'error',
                'message' => "Mail type '{$params['mail_type_code']}' doesn't exist.",
            ];
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, $result);
            return $response;
        }

        $msr = (new MailSubscribeRequest)
            ->setMailTypeCode($params['mail_type_code'])
            ->setMailTypeId($mailType->id)
            ->setUser($user);
        if ($params['variant_code']) {
            $msr->setVariantCode($params['variant_code']);
        } elseif ($params['variant_id']) {
            $msr->setVariantId($params['variant_id']);
        }

        $rtmParams = array_filter([
            'rtm_source' => $params['rtm_source'] ?? null,
            'rtm_medium' => $params['rtm_medium'] ?? null,
            'rtm_campaign' => $params['rtm_campaign'] ?? null,
            'rtm_content' => $params['rtm_content'] ?? null,
            'rtm_variant' => $params['rtm_variant'] ?? null,
        ]);

        $subscribedVariants = [];
        try {
            if ($this->getAction() === 'subscribe') {
                $mailSubscribeResponse = $this->mailUserSubscriptionsRepository->subscribe($msr, $rtmParams);
                $subscribedVariants = $mailSubscribeResponse->getSubscribedVariants() ?? [];
            } else {
                $this->mailUserSubscriptionsRepository->unsubscribe($msr, $rtmParams);
            }
        } catch (MailerException $exception) {
            $code = $exception->getCode() ?: Response::S400_BAD_REQUEST;

            return new JsonApiResponse($code, [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }

        return new JsonApiResponse(Response::S200_OK, array_filter([
            'status' => 'ok',
            'subscribed_variants' => $subscribedVariants,
        ]));
    }

    private function getAction()
    {
        $parts = explode('/', $this->request->getUrl()->getPath());
        if ($parts[count($parts) - 1] === 'subscribe') {
            return 'subscribe';
        } else {
            return 'unsubscribe';
        }
    }
}
