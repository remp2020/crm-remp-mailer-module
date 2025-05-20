<?php

namespace Crm\RempMailerModule\Scenarios;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Models\Database\ActiveRowFactory;
use Crm\PaymentsModule\Models\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\RempMailerModule\Repositories\MailTemplatesRepository;
use Crm\ScenariosModule\Events\NotificationTemplateParamsTrait;
use Crm\ScenariosModule\Events\ScenarioGenericEventInterface;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;

class SendNotificationEmailToAddressesGenericEvent implements ScenarioGenericEventInterface
{
    use NotificationTemplateParamsTrait;

    private array $allowedMailTypeCodes = [];

    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly Emitter $emitter,
        private readonly MailTemplatesRepository $mailTemplatesRepository,
        private readonly AddressesRepository $addressesRepository,
        private readonly ActiveRowFactory $activeRowFactory,
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly RecurrentPaymentsResolver $recurrentPaymentsResolver,
        private readonly ApplicationConfig $applicationConfig,
    ) {
    }

    public function addAllowedMailTypeCodes(string ...$mailTypeCodes): void
    {
        foreach ($mailTypeCodes as $mailTypeCode) {
            $this->allowedMailTypeCodes[$mailTypeCode] = $mailTypeCode;
        }
    }

    public function getLabel(): string
    {
        return 'Send notification email to addresses';
    }

    public function getParams(): array
    {
        $mailTemplates = $this->mailTemplatesRepository->all($this->allowedMailTypeCodes);

        $mailTemplateOptions = [];
        foreach ($mailTemplates as $mailTemplate) {
            $mailTemplateOptions[$mailTemplate->code] = $mailTemplate->name;
        }

        return [
            new StringLabeledArrayParam('email_addresses', 'Email addresses', [], 'and', true),
            new StringLabeledArrayParam('email_codes', 'Email codes', $mailTemplateOptions, 'and'),
        ];
    }

    public function createEvents($options, $params): array
    {
        $templateParams = $this->getNotificationTemplateParams($params);

        $events = [];
        foreach ($options['email_addresses']->selection as $emailAddress) {
            $userRow = $this->activeRowFactory->create([
                'email' => $emailAddress,
            ]);

            foreach ($options['email_codes']->selection as $emailCode) {
                $events[] = new NotificationEvent(
                    $this->emitter,
                    $userRow,
                    $emailCode,
                    $templateParams,
                );
            }
        }
        return $events;
    }
}
