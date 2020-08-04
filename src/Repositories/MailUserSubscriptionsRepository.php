<?php

namespace Crm\RempMailerModule\Repositories;

use Crm\RempMailerModule\Events\UserMailSubscriptionsChanged;
use Crm\RempMailerModule\Models\Api\Client;
use Crm\RempMailerModule\Models\Api\MailSubscribeRequest;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;

class MailUserSubscriptionsRepository
{
    public $usersRepository;

    private $apiClient;

    private $emitter;

    public function __construct(Client $apiClient, UsersRepository $usersRepository, Emitter $emitter)
    {
        $this->apiClient = $apiClient;
        $this->usersRepository = $usersRepository;
        $this->emitter = $emitter;
    }

    final public function userPreferences(int $userId, ?bool $subscribed = null)
    {
        $mailSubscriptions = [];

        $email = $this->usersRepository->find($userId)->email;
        $preferences = $this->apiClient->getUserPreferences($userId, $email, $subscribed);

        foreach ($preferences as $preference) {
            $mailSubscriptions[$preference['id']] = $preference;
        }

        return $mailSubscriptions;
    }

    final public function subscribeUser($user, $mailTypeId, $variantId = null, $utmParams = [])
    {
        $result = $this->apiClient->subscribeUser($user->id, $user->email, $mailTypeId, $variantId, $utmParams);
        $this->emitter->emit(new UserMailSubscriptionsChanged($user->id, $mailTypeId));
        return $result;
    }

    final public function unSubscribeUser($user, $mailTypeId, $utmParams = [])
    {
        $result = $this->apiClient->unSubscribeUser($user->id, $user->email, $mailTypeId, $utmParams);
        $this->emitter->emit(new UserMailSubscriptionsChanged($user->id, $mailTypeId));
        return $result;
    }

    final public function subscribeUserAll($user)
    {
        $subscribedMailTypes = $this->userPreferences($user->id, false);
        $subscribeRequests = [];
        foreach ($subscribedMailTypes as $subscribedMailType) {
            if ($subscribedMailType['is_subscribed']) {
                continue;
            }
            $subscribeRequests[] = (new MailSubscribeRequest())
                ->setUser($user)
                ->setSubscribed(true)
                ->setMailTypeCode($subscribedMailType['code']);
        }
        return $this->apiClient->bulkSubscribe($subscribeRequests);
    }

    final public function unsubscribeUserAll($user)
    {
        $subscribedMailTypes = $this->userPreferences($user->id, true);
        $subscribeRequests = [];
        foreach ($subscribedMailTypes as $subscribedMailType) {
            if (!$subscribedMailType['is_subscribed']) {
                continue;
            }
            $subscribeRequests[] = (new MailSubscribeRequest())
                ->setUser($user)
                ->setSubscribed(false)
                ->setMailTypeCode($subscribedMailType['code']);
        }
        return $this->apiClient->bulkSubscribe($subscribeRequests);
    }

    final public function bulkSubscriptionChange(array $subscribeRequests)
    {
        return $this->apiClient->bulkSubscribe($subscribeRequests);
    }

    final public function isUserUnsubscribed($user, $mailTypeId)
    {
        return $this->apiClient->isUserUnsubscribed($user->id, $user->email, $mailTypeId);
    }

    final public function unSubscribeUserVariant($user, $mailTypeId, $variantId, $utmParams = [])
    {
        return $this->apiClient->unSubscribeUserVariant($user->id, $user->email, $mailTypeId, $variantId, $utmParams);
    }
}
