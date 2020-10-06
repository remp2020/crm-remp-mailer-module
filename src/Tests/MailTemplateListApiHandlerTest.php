<?php

namespace Crm\RempMailerModule\Tests;

use Crm\ApiModule\Authorization\NoAuthorization;
use Crm\RempMailerModule\Api\MailTemplateListApiHandler;
use Crm\RempMailerModule\Models\Api\Client;
use Crm\RempMailerModule\Repositories\MailTemplatesRepository;
use Nette\Http\Response;
use PHPUnit\Framework\TestCase;

class MailTemplateListApiHandlerTest extends TestCase
{
    public function testListing()
    {
        $mailTemplate = (object)[
            'code' => 'mail_code',
            'name' => 'mail_name',
            'description' => 'mail_name',
            'mail_type' => (object)[
                'code' => 'mail_type_code',
                'title' => 'mail_type_title',
                'description' => 'mail_type_desc',
                'sorting' => '100',
            ],
        ];

        $client = \Mockery::mock(Client::class)
            ->shouldReceive('getTemplates')
            ->andReturn([$mailTemplate])
            ->getMock();

        $mailTemplateListApiHandler = new MailTemplateListApiHandler(new MailTemplatesRepository($client));
        $mailTemplateListApiHandler->addAllowedMailTypeCodes('test_templates');
        $response = $mailTemplateListApiHandler->handle(new NoAuthorization());
        $this->assertEquals(Response::S200_OK, $response->getHttpCode());

        $payload = $response->getPayload();
        $this->assertEquals('ok', $payload['status']);
        $this->assertCount(1, $payload['mail_templates']);
        $this->assertEquals($mailTemplate->code, $payload['mail_templates'][0]['code']);
        $this->assertEquals($mailTemplate->mail_type->code, $payload['mail_templates'][0]['mail_type']['code']);
    }
}
