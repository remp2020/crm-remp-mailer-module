<?php

namespace Crm\RempMailerModule\Scenarios;

use Crm\ApplicationModule\ActiveRowFactory;
use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\RempMailerModule\Repositories\MailTemplatesRepository;
use Crm\ScenariosModule\Events\ScenarioGenericEventInterface;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;

class SendNotificationEmailToAddressesGenericEvent implements ScenarioGenericEventInterface
{
    private $usersRepository;

    private $paymentsRepository;

    private $mailTemplatesRepository;

    private $emitter;

    private $allowedMailTypeCodes = [];

    private $addressesRepository;

    private $activeRowFactory;

    public function __construct(
        UsersRepository $usersRepository,
        PaymentsRepository $paymentsRepository,
        Emitter $emitter,
        MailTemplatesRepository $mailTemplatesRepository,
        AddressesRepository $addressesRepository,
        ActiveRowFactory $activeRowFactory
    ) {
        $this->emitter = $emitter;
        $this->usersRepository = $usersRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->mailTemplatesRepository = $mailTemplatesRepository;
        $this->addressesRepository = $addressesRepository;
        $this->activeRowFactory = $activeRowFactory;
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
        $templateParams = [];

        $user = $this->usersRepository->find($params->user_id);
        $payment = isset($params->payment_id) ? $this->paymentsRepository->find($params->payment_id) : null;
        $address = isset($params->address_id) ? $this->addressesRepository->find($params->address_id) : null;

        if ($user) {
            $templateParams['user'] = $user->toArray();
        }
        if ($payment) {
            $templateParams['payment'] = $payment->toArray();
        }
        if ($address) {
            $templateParams['address'] = $address->toArray();
        }
        if (isset($payment->subscription)) {
            $templateParams['subscription'] = $payment->subscription->toArray();
        }
        if (isset($payment->subscription_type)) {
            $templateParams['subscription_type'] = $payment->subscription_type->toArray();
        }

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
                    $templateParams
                );
            }
        }
        return $events;
    }
}
