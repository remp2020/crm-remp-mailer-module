<?php

namespace Crm\RempMailerModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\RempMailerModule\Repositories\MailTemplatesRepository;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Nette\Http\Response;

class MailTemplateListApiHandler extends ApiHandler
{
    const MAIL_TYPES_ALLOWED_TO_LIST = ['system', 'system_optional'];

    private $mailTemplatesRepository;

    private $mailTypesRepository;

    public function __construct(
        MailTemplatesRepository $mailTemplatesRepository,
        MailTypesRepository $mailTypesRepository
    ) {
        $this->mailTemplatesRepository = $mailTemplatesRepository;
        $this->mailTypesRepository = $mailTypesRepository;
    }

    public function params()
    {
        return [];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $results = [];
        foreach (self::MAIL_TYPES_ALLOWED_TO_LIST as $listMailType) {
            $mailType = $this->mailTypesRepository->getByCode($listMailType);
            $mailTemplates = $this->mailTemplatesRepository->all([$listMailType]);

            foreach ($mailTemplates as $mailTemplate) {
                $results[] = [
                    'code' => $mailTemplate->code,
                    'name' => $mailTemplate->name,
                    'description' => $mailTemplate->description ?? "",
                    'mail_type' => [
                        'code' => $mailType->code,
                        'name' => $mailType->title,
                        'description' => $mailType->description ?? "",
                        'sorting' => $mailType->sorting,
                    ],
                ];
            }
        }

        $response = new JsonResponse(['status' => 'ok', 'mail_templates' => $results]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
