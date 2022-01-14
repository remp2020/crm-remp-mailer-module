<?php

namespace Crm\RempMailerModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\RempMailerModule\Repositories\MailTemplatesRepository;
use Nette\Application\LinkGenerator;
use Nette\Http\Response;

class MailTemplateListApiHandler extends ApiHandler
{
    private $mailTemplatesRepository;

    private $allowedMailTypeCodes = [];

    public function __construct(
        MailTemplatesRepository $mailTemplatesRepository,
        LinkGenerator $linkGenerator
    ) {
        $this->mailTemplatesRepository = $mailTemplatesRepository;
        $this->linkGenerator = $linkGenerator;
    }

    public function params(): array
    {
        return [];
    }

    public function addAllowedMailTypeCodes(string ...$mailTypeCodes): void
    {
        foreach ($mailTypeCodes as $mailTypeCode) {
            $this->allowedMailTypeCodes[$mailTypeCode] = $mailTypeCode;
        }
    }

    public function handle(array $params): ApiResponseInterface
    {
        $mailTemplates = $this->mailTemplatesRepository->all($this->allowedMailTypeCodes, true);
        $results = [];

        foreach ($mailTemplates as $mailTemplate) {
            $results[] = [
                'code' => $mailTemplate->code,
                'name' => $mailTemplate->name,
                'link' => $this->linkGenerator->link(
                    'RempMailer:MailTemplatesAdmin:show',
                    [
                        'code' => $mailTemplate->code,
                    ]
                ),
                'description' => $mailTemplate->description ?? "",
                'mail_type' => [
                    'code' => $mailTemplate->mail_type->code,
                    'name' => $mailTemplate->mail_type->title,
                    'description' => $mailTemplate->mail_type->description ?? "",
                    'sorting' => $mailTemplate->mail_type->sorting,
                ],
            ];
        }

        $response = new JsonResponse(['status' => 'ok', 'mail_templates' => $results]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
