<?php

namespace Crm\RempMailerModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\NumberParam;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\RempMailerModule\Repositories\MailTypeCategoriesRepository;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class UserSubscribedNewslettersCountCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'user_subscribed_newsletters_count';
    public const CATEGORIES_KEY = 'user_subscribed_newsletters_count_categories';
    public const COUNT_KEY = 'user_subscribed_newsletters_count_min';

    public function __construct(
        private readonly MailTypeCategoriesRepository $mailTypeCategoriesRepository,
        private readonly MailTypesRepository $mailTypesRepository,
        private readonly MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
        private readonly Translator $translator,
    ) {
    }

    public function params(): array
    {
        $categories = $this->mailTypeCategoriesRepository->all();

        $categoryOptions = [];
        if ($categories) {
            foreach ($categories as $category) {
                $categoryOptions[$category->code] = $category->title;
            }
        }

        return [
            new NumberParam(
                self::COUNT_KEY,
                $this->translator->translate('remp_mailer.admin.scenarios.user_subscribed_newsletters_count.count_param.label'),
                $this->translator->translate('remp_mailer.admin.scenarios.user_subscribed_newsletters_count.count_param.unit'),
                ['>='],
                ['min' => 1, 'step' => 1],
            ),
            new StringLabeledArrayParam(
                self::CATEGORIES_KEY,
                $this->translator->translate('remp_mailer.admin.scenarios.user_subscribed_newsletters_count.categories_param.label'),
                $categoryOptions,
                'or',
            ),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $categoryCodes = $paramValues[self::CATEGORIES_KEY]->selection ?? [];
        $minCount = $paramValues[self::COUNT_KEY]->selection;
        $userId = $criterionItemRow->id;

        // If no categories specified, get all mail types from all categories
        if (empty($categoryCodes)) {
            $mailTypes = $this->mailTypesRepository->all();
        } else {
            $mailTypes = $this->mailTypesRepository->getAllByCategoryCode($categoryCodes);
        }

        if (empty($mailTypes)) {
            return false;
        }

        $userSubscriptions = $this->mailUserSubscriptionsRepository->userPreferences($userId, true);
        if (empty($userSubscriptions)) {
            return false;
        }

        $subscribedCount = 0;
        foreach ($mailTypes as $mailType) {
            if (isset($userSubscriptions[$mailType->id]) && $userSubscriptions[$mailType->id]['is_subscribed']) {
                $subscribedCount++;
            }
        }

        return $subscribedCount >= $minCount;
    }

    public function label(): string
    {
        return $this->translator->translate('remp_mailer.admin.scenarios.user_subscribed_newsletters_count.label');
    }
}
