<?php

namespace Crm\RempMailerModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Params\InputParam;
use Crm\RempMailerModule\Models\Api\MailSubscribeRequest;
use Crm\RempMailerModule\Models\MailerException;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;
use Crm\UsersModule\Auth\UsersApiAuthorizationInterface;
use Nette\Http\Request;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class EmailSubscriptionApiHandler extends ApiHandler
{
    private $mailUserSubscriptionsRepository;

    private $mailTypesRepository;

    private $request;

    public function __construct(
        Request $request,
        MailTypesRepository $mailTypesRepository,
        MailUserSubscriptionsRepository $mailUserSubscriptionsRepository
    ) {
        $this->request = $request;
        $this->mailTypesRepository = $mailTypesRepository;
        $this->mailUserSubscriptionsRepository = $mailUserSubscriptionsRepository;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'mail_type_code', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'variant_code', InputParam::OPTIONAL),
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
        }

        try {
            if ($this->getAction() === 'subscribe') {
                $this->mailUserSubscriptionsRepository->subscribe($msr);
            } else {
                $this->mailUserSubscriptionsRepository->unsubscribe($msr);
            }
        } catch (MailerException $exception) {
            $code = $exception->getCode() ?: Response::S400_BAD_REQUEST;
            $response = new JsonApiResponse($code, [
                'status' => 'error',
                'message' => $exception->getMessage()
            ]);
            return $response;
        }

        $response = new JsonApiResponse(Response::S200_OK, [
            'status' => 'ok'
        ]);
        return $response;
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
