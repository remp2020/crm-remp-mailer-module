<?php

namespace Crm\RempMailerModule\Forms;

use Crm\RempMailerModule\Models\Api\MailSubscribeRequest;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Kdyby\Translation\Translator;
use Nette\Application\UI\Form;
use Nette\Utils\Html;
use Tomaj\Form\Renderer\BootstrapRenderer;

class EmailSettingsFormFactory
{
    private $mailTypesRepository;

    private $mailUserSubscriptionsRepository;

    private $usersRepository;

    private $translator;

    /** @var integer */
    private $userId;

    private $simple = false;

    /* callback function */
    public $onUpdate;

    public function __construct(
        MailTypesRepository $mailTypesRepository,
        MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
        UsersRepository $usersRepository,
        Translator $translator
    ) {
        $this->mailTypesRepository = $mailTypesRepository;
        $this->mailUserSubscriptionsRepository = $mailUserSubscriptionsRepository;
        $this->usersRepository = $usersRepository;
        $this->translator = $translator;
    }

    /**
     * @return Form
     */
    public function create($userId, $simple = false)
    {
        $this->userId = $userId;
        $this->simple = $simple;

        $form = new Form;
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();

        if (!$simple) {
            $form->addGroup('remp_mailer.admin.mail_settings.frontend_header');
        }

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

        foreach ($mailTypes as $mailType) {
            if ($mailType->locked && !$simple) {
                continue;
            }
            $title = $mailType->title;

            if (isset($mailSubscriptions[$mailType->id])) {
                if ($mailSubscriptions[$mailType->id]['is_subscribed']) {
                    $title .= ' <small class="text-muted" style="font-size:0.7em">(' . $this->translator->translate('remp_mailer.admin.mail_settings.subscribed', ['time' => $mailSubscriptions[$mailType->id]['updated_at']]) . ')</small>';
                } else {
                    $title .= ' <small class="text-muted" style="font-size:0.7em">(' . $this->translator->translate('remp_mailer.admin.mail_settings.unsubscribed', ['time' => $mailSubscriptions[$mailType->id]['updated_at']]) . ')</small>';
                }
            }

            $checkbox = $form->addCheckbox('type_' . $mailType->id, Html::el('span')->setHtml($title));
            if (!$simple) {
                $checkbox->setOption('description', Html::el('span', ['class' => 'help-block'])->setHtml($mailType->description));
            }

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

            if (isset($mailSubscriptions[$mailType->id])) {
                $defaults['type_' . $mailType->id] = $mailSubscriptions[$mailType->id]['is_subscribed'];
                if (!$mailType->is_multi_variant && $mailSubscriptions[$mailType->id]['variants']) {
                    foreach ($mailSubscriptions[$mailType->id]['variants'] as $variant) {
                        $defaults['variant_' . $mailType->id] = $variant['id'];
                    }
                }
            } else {
                $defaults['type_' . $mailType->id] = false;
            }
        }

        if ($simple) {
            $form->addHidden('user_id', $userId);
        }

        $form->addSubmit('send', 'system.save');

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        if ($this->simple) {
            $userId = $values['user_id'];
        } else {
            if (isset($values['system'])) {
                unset($values['system']);
            }

            $userId = $this->userId;
        }

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
        foreach ($userPreferences as $mailTypeId => $mailSubscription) {
            if ($mailSubscription['is_subscribed'] === $parsedTypeValues[$mailTypeId]['is_subscribed']) {
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
