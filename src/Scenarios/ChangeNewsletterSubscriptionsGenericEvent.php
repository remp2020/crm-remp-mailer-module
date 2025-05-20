<?php

namespace Crm\RempMailerModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\RempMailerModule\Events\ChangeUserNewsletterSubscriptionsEvent;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\ScenariosModule\Events\ScenarioGenericEventInterface;

class ChangeNewsletterSubscriptionsGenericEvent implements ScenarioGenericEventInterface
{
    public function __construct(private MailTypesRepository $mailTypesRepository)
    {
    }

    public function getLabel(): string
    {
        return 'Change newsletter subscriptions';
    }

    public function getParams(): array
    {
        $mailTypes = $this->mailTypesRepository->all() ?? [];

        $mailTypeOptions = [];

        foreach ($mailTypes as $mailType) {
            $mailTypeOptions[$mailType->code] = $mailType->title;
        }

        return [
            new StringLabeledArrayParam(
                'subscribe_newsletter_codes',
                'Subscribe newsletter codes',
                $mailTypeOptions,
                'and',
            ),
            new StringLabeledArrayParam(
                'unsubscribe_newsletter_codes',
                'Unsubscribe newsletter codes',
                $mailTypeOptions,
                'and',
            ),
        ];
    }

    public function createEvents($options, $params): array
    {
        return [
            new ChangeUserNewsletterSubscriptionsEvent(
                $params->user_id,
                $options['subscribe_newsletter_codes']->selection ?? [],
                $options['unsubscribe_newsletter_codes']->selection ?? [],
            ),
        ];
    }
}
