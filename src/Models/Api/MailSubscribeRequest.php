<?php

namespace Crm\RempMailerModule\Models\Api;

use Nette\Database\Table\ActiveRow;

class MailSubscribeRequest
{
    private $userId;

    private $email;

    private $mailTypeCode;

    private $mailTypeId;

    private $subscribed;

    private $variantId;

    private $variantCode;

    private $sendAccompanyingEmails;

    private $keepMailTypeSubscription;

    public function setUser(ActiveRow $user)
    {
        $this->userId = $user->id;
        $this->email = $user->email;
        return $this;
    }

    public function setMailTypeCode(string $code)
    {
        $this->mailTypeCode = $code;
        return $this;
    }

    public function setMailTypeId(int $id)
    {
        $this->mailTypeId = $id;
        return $this;
    }

    public function setSubscribed(bool $subscribed)
    {
        $this->subscribed = $subscribed;
        return $this;
    }

    public function setVariantId(int $variantId)
    {
        $this->variantId = $variantId;
        return $this;
    }

    public function setVariantCode(string $variantCode)
    {
        $this->variantCode = $variantCode;
        return $this;
    }

    public function setSendAccompanyingEmails(bool $sendAccompanyingEmails)
    {
        $this->sendAccompanyingEmails = $sendAccompanyingEmails;
        return $this;
    }

    public function setKeepMailTypeSubscription(bool $keepMailTypeSubscription)
    {
        $this->keepMailTypeSubscription = $keepMailTypeSubscription;
    }

    public function getRequestData()
    {
        return array_filter([
            'email' => $this->email,
            'user_id' => $this->userId,
            'subscribe' => $this->subscribed,
            'list_id' => $this->mailTypeId,
            'list_code' => $this->mailTypeCode,
            'variant_id' => $this->variantId,
            'variant_code' => $this->variantCode,
            'send_accompanying_emails' => $this->sendAccompanyingEmails,
            'keep_list_subscription' => $this->keepMailTypeSubscription,
        ], function ($item) {
            return $item !== null;
        });
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getMailTypeCode()
    {
        return $this->mailTypeCode;
    }

    public function getMailTypeId()
    {
        return $this->mailTypeId;
    }

    public function getSubscribed()
    {
        return $this->subscribed;
    }

    public function getVariantId()
    {
        return $this->variantId;
    }

    public function getVariantCode()
    {
        return $this->variantId;
    }
}
