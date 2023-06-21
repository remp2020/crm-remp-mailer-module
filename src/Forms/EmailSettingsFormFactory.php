<?php

namespace Crm\RempMailerModule\Forms;

use Crm\ApplicationModule\Helpers\UserDateHelper;
use Crm\RempMailerModule\Models\Api\MailSubscribeRequest;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;
use Nette\Utils\Html;
use Tomaj\Form\Renderer\BootstrapRenderer;

class EmailSettingsFormFactory
{
    /* callback function */
    public $onUpdate;

    public function __construct(
        private MailTypesRepository $mailTypesRepository,
        private MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
        private UsersRepository $usersRepository,
        private Translator $translator,
        private UserDateHelper $userDateHelper,
    ) {
    }

    /**
     * @return Form
     */
    public function create(?int $userId = null)
    {
        $form = new Form;
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();

        $form->addHidden('user_id', $userId);

        /** @var \stdClass[]|null $mailTypes */
        $mailTypes = $this->mailTypesRepository->all();
        if (!$mailTypes) {
            $form->addError($this->translator->translate('mailer.admin.mail_settings.mail_types_error'));
            return $form;
        }

        $defaults = [];

        $mailSubscriptions = [];
        if ($userId) {
            $mailSubscriptions = $this->mailUserSubscriptionsRepository->userPreferences($userId);
        }

        $subscribedGroup = $form->addGroup('subscribed')
            ->setOption('label', null);
        $unsubscribedGroup = $form->addGroup('unsubscribed')
            ->setOption('container', 'div class="collapse"')
            ->setOption('label', null)
            ->setOption('id', 'mailSettingsCollapse');
        $buttonsGroup = $form->addGroup('buttons')
            ->setOption('label', null);

        foreach ($mailTypes as $mailType) {
            $title = $mailType->title;
            $isSubscribed = $mailSubscriptions[$mailType->id]['is_subscribed'] ?? false;

            if (isset($mailSubscriptions[$mailType->id])) {
                $updatedAt = $this->userDateHelper->process(
                    date: DateTime::from($mailSubscriptions[$mailType->id]['updated_at']),
                );

                if ($isSubscribed) {
                    $title .= ' <small class="text-muted" style="font-size:0.7em">(' . $this->translator->translate('remp_mailer.admin.mail_settings.subscribed', ['time' => $updatedAt]) . ')</small>';
                } else {
                    $title .= ' <small class="text-muted" style="font-size:0.7em">(' . $this->translator->translate('remp_mailer.admin.mail_settings.unsubscribed', ['time' => $updatedAt]) . ')</small>';
                }
            }

            if ($isSubscribed) {
                $form->setCurrentGroup($subscribedGroup);
            } else {
                $form->setCurrentGroup($unsubscribedGroup);
            }

            $checkbox = $form->addCheckbox('type_' . $mailType->id, Html::el('span')->setHtml($title));

            if (!empty($mailType->variants)) {
                if ($mailType->is_multi_variant) {
                    if (isset($mailSubscriptions[$mailType->id]['variants']) && count($mailSubscriptions[$mailType->id]['variants'])) {
                        $checkbox->setOption('description', 'Subscribed  ' . count($mailSubscriptions[$mailType->id]['variants']) . '/' . count((array) $mailType->variants) . ' variants');
                    }
                } else {
                    $form->addSelect('variant_' . $mailType->id, '', (array) $mailType->variants)
                        ->addConditionOn($checkbox, Form::EQUAL, true)->setRequired();
                }
            }

            $checkbox->setDefaultValue($isSubscribed);

            if (isset($mailSubscriptions[$mailType->id])) {
                if (!$mailType->is_multi_variant && $mailSubscriptions[$mailType->id]['variants']) {
                    foreach ($mailSubscriptions[$mailType->id]['variants'] as $variant) {
                        $defaults['variant_' . $mailType->id] = $variant['id'];
                    }
                }
            }
        }

        $form->setCurrentGroup($buttonsGroup);

        $form->addSubmit('send')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('system.save'));

        $form->addButton('more')
            ->setHtmlAttribute('data-toggle', 'collapse')
            ->setHtmlAttribute('data-target', '#mailSettingsCollapse')
            ->setHtmlAttribute('class', 'btn btn-default')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fas fa-caret-down"></i> ' . $this->translator->translate('remp_mailer.admin.mail_settings.show_unsubscribed'));

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded(Form $form, ArrayHash $values)
    {
        if (isset($values['system'])) {
            unset($values['system']);
        }

        $userId = $values['user_id'];

        $parsedTypeValues = [];
        foreach ($values as $key => $value) {
            if (substr($key, 0, 5) === 'type_') {
                $parsedTypeValues[str_replace('type_', '', $key)] = ['is_subscribed' => $value];
            }
        }
        foreach ($values as $key => $value) {
            if (substr($key, 0, 8) === 'variant_') {
                $parsedTypeValues[str_replace('variant_', '', $key)]['variant_id'] = $value;
            }
        }

        $user = $this->usersRepository->find($userId);

        $subscribeRequests = [];
        $userPreferences = $this->mailUserSubscriptionsRepository->userPreferences($values['user_id']);
        foreach ($parsedTypeValues as $mailTypeId => $values) {
            $isSubscribed = $userPreferences[$mailTypeId]['is_subscribed'] ?? false;
            if ($isSubscribed === $parsedTypeValues[$mailTypeId]['is_subscribed']) {
                continue;
            }
            $request = (new MailSubscribeRequest())
                ->setUser($user)
                ->setMailTypeId($mailTypeId)
                ->setSubscribed($parsedTypeValues[$mailTypeId]['is_subscribed']);
            if (isset($parsedTypeValues[$mailTypeId]['variant_id'])) {
                $request->setVariantId((int) $parsedTypeValues[$mailTypeId]['variant_id']);
            }
            $subscribeRequests[] = $request;
        }
        $this->mailUserSubscriptionsRepository->bulkSubscriptionChange($subscribeRequests);

        $this->onUpdate->__invoke($this, $userId);
    }
}
