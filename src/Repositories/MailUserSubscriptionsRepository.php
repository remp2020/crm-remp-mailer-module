<?php

namespace Crm\RempMailerModule\Repositories;

use Crm\RempMailerModule\Events\UserMailSubscriptionsChanged;
use Crm\RempMailerModule\Models\Api\Client;
use Crm\RempMailerModule\Models\Api\MailSubscribeRequest;
use Crm\RempMailerModule\Models\Api\MailSubscribeResponse;
use Crm\RempMailerModule\Models\MailerException;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;

class MailUserSubscriptionsRepository
{
    public function __construct(
        private Client $apiClient,
        private UsersRepository $usersRepository,
        private MailTypesRepository $mailTypesRepository,
        private Emitter $emitter
    ) {
    }

    final public function userPreferences(int $userId, ?bool $subscribed = null): ?array
    {
        $user = $this->usersRepository->find($userId);
        if ($user->deleted_at) {
            return [];
        }

        $preferences = $this->apiClient->getUserPreferences($user->id, $user->email, $subscribed) ?? [];

        $mailSubscriptions = [];
        foreach ($preferences as $preference) {
            $variants = $preference['variants'];
            $preference['variants'] = [];
            foreach ($variants as $variant) {
                $preference['variants'][$variant['id']] = $variant;
            }
            $mailSubscriptions[$preference['id']] = $preference;
        }

        return $mailSubscriptions;
    }

    /**
     * @param $user
     * @param $mailTypeId
     * @param null $variantId
     * @param array $rtmParams
     * @return bool
     * @throws MailerException
     * @deprecated Recommended to use MailUserSubscriptionsRepository::subscribe method instead.
     */
    final public function subscribeUser($user, $mailTypeId, $variantId = null, $rtmParams = [])
    {
        $result = $this->apiClient->subscribeUser($user->id, $user->email, $mailTypeId, $variantId, $rtmParams);
        $this->emitter->emit(new UserMailSubscriptionsChanged(
            $user->id,
            $mailTypeId,
            UserMailSubscriptionsChanged::SUBSCRIBED
        ));
        return $result;
    }

    /**
     * @param $user
     * @param $mailTypeId
     * @param array $rtmParams
     * @return bool
     * @throws MailerException
     * @deprecated Recommended to use MailUserSubscriptionsRepository::unsubscribe method instead.
     */
    final public function unSubscribeUser($user, $mailTypeId, $rtmParams = [])
    {
        $result = $this->apiClient->unSubscribeUser($user->id, $user->email, $mailTypeId, $rtmParams);
        $this->emitter->emit(new UserMailSubscriptionsChanged(
            $user->id,
            $mailTypeId,
            UserMailSubscriptionsChanged::UNSUBSCRIBED
        ));
        return $result;
    }

    /**
     * @param MailSubscribeRequest $msr
     * @param array                $rtmParams
     *
     * @return MailSubscribeResponse
     * @throws MailerException
     */
    final public function subscribe(MailSubscribeRequest $msr, array $rtmParams = []): MailSubscribeResponse
    {
        $msr = $msr->setSubscribed(true);
        $mailSubscribeResponse = $this->apiClient->subscribe($msr, $rtmParams);

        $this->emitter->emit(new UserMailSubscriptionsChanged(
            $msr->getUserId(),
            $msr->getMailTypeId(),
            UserMailSubscriptionsChanged::SUBSCRIBED
        ));

        return $mailSubscribeResponse;
    }

    /**
     * @param MailSubscribeRequest $msr
     * @param array $rtmParams
     * @return bool
     * @throws MailerException
     */
    final public function unsubscribe(MailSubscribeRequest $msr, array $rtmParams = []): bool
    {
        $msr = $msr->setSubscribed(false);
        $result = $this->apiClient->unsubscribe($msr, $rtmParams);

        $this->emitter->emit(new UserMailSubscriptionsChanged(
            $msr->getUserId(),
            $msr->getMailTypeId(),
            UserMailSubscriptionsChanged::UNSUBSCRIBED
        ));

        return $result;
    }

    final public function subscribeUserAll($user)
    {
        $subscribedMailTypes = $this->userPreferences($user->id, false);

        $subscribedMailTypeCodes = array_column($subscribedMailTypes, 'code');
        $mailTypes = [];
        foreach ($this->mailTypesRepository->getAllByCode($subscribedMailTypeCodes) as $mailType) {
            $mailTypes[$mailType->code] = $mailType;
        }

        $subscribeRequests = [];
        foreach ($subscribedMailTypes as $subscribedMailType) {
            if ($mailTypes[$subscribedMailType['code']]->locked ||
                $subscribedMailType['is_subscribed']) {
                continue;
            }
            $subscribeRequests[] = (new MailSubscribeRequest())
                ->setUser($user)
                ->setSubscribed(true)
                ->setMailTypeId($subscribedMailType['id'])
                ->setMailTypeCode($subscribedMailType['code'])
                ->setSendAccompanyingEmails(false);
        }
        $result = $this->apiClient->bulkSubscribe($subscribeRequests);

        /** @var MailSubscribeRequest $subscribeRequest */
        foreach ($subscribeRequests as $subscribeRequest) {
            $this->emitter->emit(new UserMailSubscriptionsChanged(
                $subscribeRequest->getUserId(),
                $subscribeRequest->getMailTypeId(),
                UserMailSubscriptionsChanged::SUBSCRIBED
            ));
        }

        return $result;
    }

    final public function unsubscribeUserAll($user)
    {
        $subscribedMailTypes = $this->userPreferences($user->id, true);

        $subscribedMailTypeCodes = array_column($subscribedMailTypes, 'code');
        $mailTypes = [];
        foreach ($this->mailTypesRepository->getAllByCode($subscribedMailTypeCodes) as $mailType) {
            $mailTypes[$mailType->code] = $mailType;
        }

        $subscribeRequests = [];
        foreach ($subscribedMailTypes as $subscribedMailType) {
            if ($mailTypes[$subscribedMailType['code']]->locked ||
                !$subscribedMailType['is_subscribed']) {
                continue;
            }
            $subscribeRequests[] = (new MailSubscribeRequest())
                ->setUser($user)
                ->setSubscribed(false)
                ->setMailTypeId($subscribedMailType['id'])
                ->setSendAccompanyingEmails(false);
        }
        $result = $this->apiClient->bulkSubscribe($subscribeRequests);

        /** @var MailSubscribeRequest $subscribeRequest */
        foreach ($subscribeRequests as $subscribeRequest) {
            $this->emitter->emit(new UserMailSubscriptionsChanged(
                $subscribeRequest->getUserId(),
                $subscribeRequest->getMailTypeId(),
                UserMailSubscriptionsChanged::UNSUBSCRIBED
            ));
        }

        return $result;
    }

    final public function bulkSubscriptionChange(array $subscribeRequests)
    {
        $result = $this->apiClient->bulkSubscribe($subscribeRequests);

        /** @var MailSubscribeRequest $subscribeRequest */
        foreach ($subscribeRequests as $subscribeRequest) {
            $this->emitter->emit(new UserMailSubscriptionsChanged(
                $subscribeRequest->getUserId(),
                $subscribeRequest->getMailTypeId(),
                $subscribeRequest->getSubscribed() ?
                    UserMailSubscriptionsChanged::SUBSCRIBED : UserMailSubscriptionsChanged::UNSUBSCRIBED
            ));
        }

        return $result;
    }

    final public function isUserUnsubscribed($user, $mailTypeId): bool
    {
        $response = $this->apiClient->isUserUnsubscribed($user->id, $user->email, $mailTypeId);
        return (bool) $response->is_unsubscribed;
    }

    final public function isUserSubscribed($user, $mailTypeId, $variantId = null): bool
    {
        $response = $this->apiClient->isUserSubscribed($user->id, $user->email, $mailTypeId, $variantId);
        return (bool) $response->is_subscribed;
    }
}
