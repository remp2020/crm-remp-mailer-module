<?php

namespace Crm\RempMailerModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\TimeframeParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\RempMailerModule\Models\Api\MailLogQueryBuilder;
use Crm\RempMailerModule\Repositories\MailLogsRepository;
use Crm\RempMailerModule\Repositories\MailTemplatesRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class MailReceivedCriteria implements ScenariosCriteriaInterface
{
    const KEY = 'mail_received';
    const TEMPLATE_KEY = 'mail_received_template';
    const TIMEFRAME_KEY = 'mail_received_timeframe';

    public const OPERATOR_IN_THE_LAST = 'in the last';
    public const OPERATOR_BEFORE = 'before';
    private const OPERATORS = [
        self::OPERATOR_IN_THE_LAST,
        self::OPERATOR_BEFORE,
    ];

    private const UNITS = ['days', 'weeks', 'months', 'years'];

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
                'or'
            ),
            new TimeframeParam(
                self::TIMEFRAME_KEY,
                '',
                $this->translator->translate('remp_mailer.admin.scenarios.mail_received.timeframe_param.amount_label'),
                $this->translator->translate('remp_mailer.admin.scenarios.mail_received.timeframe_param.units_label'),
                array_values(self::OPERATORS),
                self::UNITS
            )
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

        if (isset(
            $paramValues[self::TIMEFRAME_KEY],
            $paramValues[self::TIMEFRAME_KEY]->operator,
            $paramValues[self::TIMEFRAME_KEY]->unit,
            $paramValues[self::TIMEFRAME_KEY]->selection
        )) {
            $timeframeOperator = array_search($paramValues[self::TIMEFRAME_KEY]->operator, self::OPERATORS, true);
            if ($timeframeOperator === false) {
                throw new \Exception("Timeframe operator [{$timeframeOperator}] is not a valid operator out of: " . Json::encode(array_values(self::OPERATORS)));
            }
            $timeframeUnit = $paramValues[self::TIMEFRAME_KEY]->unit;
            if (!in_array($timeframeUnit, self::UNITS, true)) {
                throw new \Exception("Timeframe unit [{$timeframeUnit}] is not a valid unit out of: " . Json::encode(self::UNITS));
            }
            $timeframeValue = $paramValues[self::TIMEFRAME_KEY]->selection;
            if (filter_var($timeframeValue, FILTER_VALIDATE_INT, array("options" => array("min_range"=> 0))) === false) {
                throw new \Exception("Timeframe value [{$timeframeValue}] is not a valid value. It has to be positive integer.");
            }


            $limitAt = (new DateTime())->modify('-' . $timeframeValue . $timeframeUnit);
            $timeframeOperator = self::OPERATORS[$timeframeOperator];

            $deliveredAtTimeFilter = [];
            if ($timeframeOperator === self::OPERATOR_BEFORE) {
                $deliveredAtTimeFilter['to'] = $limitAt;
            } elseif ($timeframeOperator === self::OPERATOR_IN_THE_LAST) {
                $deliveredAtTimeFilter['from'] = $limitAt;
            }

            $logQuery = new MailLogQueryBuilder();
            $logQuery->setEmail($criterionItemRow->email)
                ->setMailTemplateCodes($templateCodes)
                ->setFilter('delivered_at', $deliveredAtTimeFilter)
                ->setPage(1);

            $result = $this->mailLogsRepository->get($logQuery);

            if (!empty($result)) {
                return true;
            }
        }

        return false;
    }

    public function label(): string
    {
        return $this->translator->translate('remp_mailer.admin.scenarios.mail_received.label');
    }
}
