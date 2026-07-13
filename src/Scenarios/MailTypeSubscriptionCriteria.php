<?php

namespace Crm\RempMailerModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Json;

class MailTypeSubscriptionCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'mail_type_subscription';

    public function __construct(
        private readonly MailTypesRepository $mailTypesRepository,
        private readonly MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
        private readonly Translator $translator,
    ) {
    }

    public function params(): array
    {
        $mailTypes = $this->mailTypesRepository->all(
            publicListing: true,
            includeVariantsData: true,
        ) ?? [];

        usort($mailTypes, function ($a, $b) {
            return [$a->is_multi_variant, $a->sorting] <=> [$b->is_multi_variant, $b->sorting];
        });

        $options = [];
        foreach ($mailTypes as $mailType) {
            if ($mailType->is_external) {
                continue;
            }
            $variants = (array) ($mailType->variants ?? []);
            if ($variants) {
                foreach ($variants as $variant) {
                    $options[$this->encodeFormSelectKey($mailType->code, $variant->code)] = [
                        'label' => "{$mailType->title}: {$variant->title}",
                        'group' => $mailType->is_multi_variant ? $mailType->title : 'Default',
                    ];
                }
            } else {
                $options[$this->encodeFormSelectKey($mailType->code)] = [
                    'label' => $mailType->title,
                    'group' => 'Default',
                ];
            }
        }

        return [
            new StringLabeledArrayParam(
                self::KEY,
                $this->translator->translate('remp_mailer.admin.scenarios.mail_type_subscription.mail_type_param.label'),
                $options,
                'or',
            ),
        ];
    }

    public function encodeFormSelectKey(string $mailTypeCode, ?string $variantCode = null): string
    {
        return Json::encode(array_filter([
            'mail_type' => $mailTypeCode,
            'variant' => $variantCode,
        ]));
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY]->selection ?? [];
        if (empty($values)) {
            return false;
        }

        $selectedTypeCodes = [];
        $selectedVariantsByType = [];
        foreach ($values as $selectionKey) {
            $decoded = Json::decode($selectionKey, forceArrays: true);
            if (isset($decoded['variant'])) {
                $selectedVariantsByType[$decoded['mail_type']][] = $decoded['variant'];
            } else {
                $selectedTypeCodes[$decoded['mail_type']] = true;
            }
        }

        $userPreferences = $this->mailUserSubscriptionsRepository->userPreferences($criterionItemRow->id, subscribed: true) ?? [];
        if (empty($userPreferences)) {
            return false;
        }

        $userPreferencesByCode = [];
        foreach ($userPreferences as $preference) {
            $userPreferencesByCode[$preference['code']] = $preference;
        }

        foreach (array_keys($selectedTypeCodes) as $typeCode) {
            if (!empty($userPreferencesByCode[$typeCode]['is_subscribed'])) {
                return true;
            }
        }

        foreach ($selectedVariantsByType as $typeCode => $variantCodes) {
            $variants = $userPreferencesByCode[$typeCode]['variants'] ?? [];
            foreach ($variants as $variant) {
                if (in_array($variant['code'], $variantCodes, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function label(): string
    {
        return $this->translator->translate('remp_mailer.admin.scenarios.mail_type_subscription.label');
    }
}
