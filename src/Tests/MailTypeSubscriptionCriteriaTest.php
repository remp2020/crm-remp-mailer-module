<?php

declare(strict_types=1);

namespace Crm\RempMailerModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\RempMailerModule\Models\Api\Client;
use Crm\RempMailerModule\Scenarios\MailTypeSubscriptionCriteria;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Mockery;

class MailTypeSubscriptionCriteriaTest extends DatabaseTestCase
{
    private Client|Mockery\MockInterface $apiClient;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        $this->refreshContainer();
        parent::setUp();

        $this->apiClient = Mockery::mock(Client::class);
        $this->container->removeService('mailerApiClient');
        $this->container->addService('mailerApiClient', $this->apiClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testUserSubscribedToSelectedMailTypeReturnsTrue(): void
    {
        [$userRow, $userSelection] = $this->prepareUserData();

        $this->apiClient->shouldReceive('getUserPreferences')
            ->andReturn([
                [
                    'id' => 1,
                    'code' => 'newsletter',
                    'title' => 'Newsletter',
                    'is_subscribed' => true,
                    'variants' => [],
                ],
            ]);

        $criteria = $this->inject(MailTypeSubscriptionCriteria::class);

        $selectionKey = $criteria->encodeFormSelectKey('newsletter');
        $paramValues = [
            MailTypeSubscriptionCriteria::KEY => (object) ['selection' => [$selectionKey]],
        ];

        $result = $criteria->addConditions($userSelection, $paramValues, $userRow);
        $this->assertTrue($result);
    }

    public function testUserSubscribedToSelectedVariantReturnsTrue(): void
    {
        [$userRow, $userSelection] = $this->prepareUserData();

        $this->apiClient->shouldReceive('getUserPreferences')
            ->andReturn([
                [
                    'id' => 1,
                    'code' => 'newsletter',
                    'title' => 'Newsletter',
                    'is_subscribed' => true,
                    'variants' => [
                        ['id' => 10, 'code' => 'weekly', 'title' => 'Weekly'],
                    ],
                ],
            ]);

        $criteria = $this->inject(MailTypeSubscriptionCriteria::class);

        $selectionKey = $criteria->encodeFormSelectKey('newsletter', 'weekly');
        $paramValues = [
            MailTypeSubscriptionCriteria::KEY => (object) ['selection' => [$selectionKey]],
        ];

        $result = $criteria->addConditions($userSelection, $paramValues, $userRow);
        $this->assertTrue($result);
    }

    public function testUserSubscribedToOtherVariantReturnsFalse(): void
    {
        [$userRow, $userSelection] = $this->prepareUserData();

        $this->apiClient->shouldReceive('getUserPreferences')
            ->andReturn([
                [
                    'id' => 1,
                    'code' => 'newsletter',
                    'title' => 'Newsletter',
                    'is_subscribed' => true,
                    'variants' => [
                        ['id' => 11, 'code' => 'monthly', 'title' => 'Monthly'],
                    ],
                ],
            ]);

        $criteria = $this->inject(MailTypeSubscriptionCriteria::class);

        $selectionKey = $criteria->encodeFormSelectKey('newsletter', 'weekly');
        $paramValues = [
            MailTypeSubscriptionCriteria::KEY => (object) ['selection' => [$selectionKey]],
        ];

        $result = $criteria->addConditions($userSelection, $paramValues, $userRow);
        $this->assertFalse($result);
    }

    public function testUserSubscribedToOtherMailTypeReturnsFalse(): void
    {
        [$userRow, $userSelection] = $this->prepareUserData();

        $this->apiClient->shouldReceive('getUserPreferences')
            ->andReturn([
                [
                    'id' => 2,
                    'code' => 'promo',
                    'title' => 'Promo',
                    'is_subscribed' => true,
                    'variants' => [],
                ],
            ]);

        $criteria = $this->inject(MailTypeSubscriptionCriteria::class);

        $selectionKey = $criteria->encodeFormSelectKey('newsletter');
        $paramValues = [
            MailTypeSubscriptionCriteria::KEY => (object) ['selection' => [$selectionKey]],
        ];

        $result = $criteria->addConditions($userSelection, $paramValues, $userRow);
        $this->assertFalse($result);
    }

    public function testEmptySelectionReturnsFalse(): void
    {
        [$userRow, $userSelection] = $this->prepareUserData();

        $criteria = $this->inject(MailTypeSubscriptionCriteria::class);

        $paramValues = [
            MailTypeSubscriptionCriteria::KEY => (object) ['selection' => []],
        ];

        $result = $criteria->addConditions($userSelection, $paramValues, $userRow);
        $this->assertFalse($result);
    }

    public function testNoUserPreferencesReturnsFalse(): void
    {
        [$userRow, $userSelection] = $this->prepareUserData();

        $this->apiClient->shouldReceive('getUserPreferences')
            ->andReturn([]);

        $criteria = $this->inject(MailTypeSubscriptionCriteria::class);

        $selectionKey = $criteria->encodeFormSelectKey('newsletter');
        $paramValues = [
            MailTypeSubscriptionCriteria::KEY => (object) ['selection' => [$selectionKey]],
        ];

        $result = $criteria->addConditions($userSelection, $paramValues, $userRow);
        $this->assertFalse($result);
    }

    private function prepareUserData(): array
    {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        // Generate unique email for each test to avoid conflicts
        $email = 'user@example.com';
        $userRow = $userManager->addNewUser($email);

        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->inject(UsersRepository::class);

        $userSelection = $usersRepository->getTable()
            ->where(['users.id' => $userRow->id]);

        return [$userRow, $userSelection];
    }
}
