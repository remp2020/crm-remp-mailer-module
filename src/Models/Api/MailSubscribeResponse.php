<?php

namespace Crm\RempMailerModule\Models\Api;

class MailSubscribeResponse
{
    private function __construct(
        private ?array $subscribedVariants = null,
    ) {
    }

    public static function fromApiResponse($decodedResponse): self
    {
        return new MailSubscribeResponse(
            subscribedVariants: $decodedResponse->subscribed_variants ?? null,
        );
    }

    public function getSubscribedVariants(): ?array
    {
        return $this->subscribedVariants;
    }
}
