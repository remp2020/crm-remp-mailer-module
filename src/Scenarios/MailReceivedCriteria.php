<?php

namespace Crm\RempMailerModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\TimeframeParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\RempMailerModule\Models\Api\MailLogQueryBuilder;
use Crm\RempMailerModule\Repositories\MailLogsRepository;
use Crm\RempMailerModule\Repositories\MailTemplatesRepository;
use Crm\ScenariosModule\Scenarios\TimeframeScenarioTrait;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class MailReceivedCriteria implements ScenariosCriteriaInterface
{
    use TimeframeScenarioTrait;

    public const string KEY = 'mail_received';
    public const string TEMPLATE_KEY = 'mail_received_template';
    public const string TIMEFRAME_KEY = self::KEY . '_timeframe';
    public const array UNITS = ['days', 'weeks', 'months', 'years'];
    public const string OPERATOR_BEFORE = 'before';
    public const string OPERATOR_IN_THE_LAST = 'in the last';
    public const array OPERATORS = [self::OPERATOR_IN_THE_LAST, self::OPERATOR_BEFORE];

    private array $allowedMailTypeCodes = [];

    public function __construct(
        private readonly MailTemplatesRepository $mailTemplatesRepository,
        private readonly MailLogsRepository $mailLogsRepository,
        private readonly Translator $translator,
    ) {
    }

    public function params(): array
    {
        $mailTemplates = $this->mailTemplatesRepository->all($this->allowedMailTypeCodes);

        $mailTemplateOptions = [];
        foreach ($mailTemplates as $mailTemplate) {
            $mailTemplateOptions[$mailTemplate->code] = $mailTemplate->name;
        }

        return [
            new StringLabeledArrayParam(
                self::TEMPLATE_KEY,
                $this->translator->translate('remp_mailer.admin.scenarios.mail_received.template_param.label'),
                $mailTemplateOptions,
                'or',
            ),
            new TimeframeParam(
                self::TIMEFRAME_KEY,
                '',
                $this->translator->translate('remp_mailer.admin.scenarios.mail_received.timeframe_param.amount_label'),
                $this->translator->translate('remp_mailer.admin.scenarios.mail_received.timeframe_param.units_label'),
                array_values(self::OPERATORS),
                self::UNITS,
            ),
        ];
    }

    public function addAllowedMailTypeCodes(string ...$mailTypeCodes): void
    {
        foreach ($mailTypeCodes as $mailTypeCode) {
            $this->allowedMailTypeCodes[$mailTypeCode] = $mailTypeCode;
        }
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $templateCodes = $paramValues[self::TEMPLATE_KEY]->selection;

        $timeframe = $this->getTimeframe($paramValues, self::UNITS, self::OPERATORS, self::TIMEFRAME_KEY);
        $operator = $timeframe['operator'] ?? null;
        $deliveredFrom = $operator === self::OPERATOR_IN_THE_LAST ? $timeframe['limit'] : null;
        $deliveredTo = $operator === self::OPERATOR_BEFORE ? $timeframe['limit'] : null;

        $logQuery = new MailLogQueryBuilder();
        $logQuery->setEmail($criterionItemRow->email)
            ->setMailTemplateCodes($templateCodes)
            ->setFilter('delivered_at', $deliveredFrom, $deliveredTo)
            ->setLimit(1)
            ->setPage(1);

        $result = $this->mailLogsRepository->get($logQuery);

        if (!empty($result)) {
            return true;
        }

        return false;
    }

    public function label(): string
    {
        return $this->translator->translate('remp_mailer.admin.scenarios.mail_received.label');
    }
}
