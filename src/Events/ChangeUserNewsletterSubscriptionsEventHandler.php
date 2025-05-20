<?php

namespace Crm\RempMailerModule\Events;

use Crm\RempMailerModule\Models\Api\MailSubscribeRequest;
use Crm\RempMailerModule\Repositories\MailTypesRepository;
use Crm\RempMailerModule\Repositories\MailUserSubscriptionsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class ChangeUserNewsletterSubscriptionsEventHandler extends AbstractListener
{
    private $mailTypesRepository;

    private $usersRepository;

    private $mailUserSubscriptionsRepository;

    public function __construct(
        MailTypesRepository $mailTypesRepository,
        UsersRepository $usersRepository,
        MailUserSubscriptionsRepository $mailUserSubscriptionsRepository,
    ) {
        $this->usersRepository = $usersRepository;
        $this->mailUserSubscriptionsRepository = $mailUserSubscriptionsRepository;
        $this->mailTypesRepository = $mailTypesRepository;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof ChangeUserNewsletterSubscriptionsEvent)) {
            throw new \Exception('invalid type of event received: ' . get_class($event));
        }

        $user = $this->usersRepository->find($event->getUserId());
        $mailTypes = $this->mailTypesRepository->all();
        if ($mailTypes === null) {
            throw new \Exception('cant fetch mail types from: ' . get_class($this->mailTypesRepository));
        }

        $newsletterChanges = $event->getChanges();

        $subscribeRequests = [];
        foreach ($newsletterChanges as $mailTypeCode => $subscribeFlag) {
            foreach ($mailTypes as $mailType) {
                if ($mailType->code === $mailTypeCode) {
                    $subscribeRequests[] = (new MailSubscribeRequest())
                        ->setUser($user)
                        ->setMailTypeId($mailType->id)
                        ->setSubscribed($subscribeFlag)
                        ->setSendAccompanyingEmails(false);
                }
            }
        }

        $this->mailUserSubscriptionsRepository
            ->bulkSubscriptionChange($subscribeRequests);
    }
}
