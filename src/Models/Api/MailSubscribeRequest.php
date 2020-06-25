<?php

namespace Crm\RempMailerModule\Models\Api;

use Nette\Database\Table\IRow;

class MailSubscribeRequest
{
    private $userId;

    private $email;

    private $mailTypeCode;

    private $mailTypeId;

    private $subscribed;

    private $variantId;

    public function setUser(IRow $user)
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

    public function getRequestData()
    {
        return array_filter([
            'email' => $this->email,
            'user_id' => $this->userId,
            'subscribe' => $this->subscribed,
            'list_id' => $this->mailTypeId,
            'list_code' => $this->mailTypeCode,
            'variant_id' => $this->variantId,
        ], function ($item) {
            return $item !== null;
        });
    }
}
