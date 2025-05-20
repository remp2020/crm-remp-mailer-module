<?php

namespace Crm\RempMailerModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\RempMailerModule\Repositories\MailTemplatesRepository;
use Nette\Application\LinkGenerator;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class MailTemplateListApiHandler extends ApiHandler
{
    private $mailTemplatesRepository;

    private $allowedMailTypeCodes = [];

    public function __construct(
        MailTemplatesRepository $mailTemplatesRepository,
        LinkGenerator $linkGenerator,
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

    public function handle(array $params): ResponseInterface
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
                    ],
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

        $response = new JsonApiResponse(Response::S200_OK, ['status' => 'ok', 'mail_templates' => $results]);

        return $response;
    }
}
