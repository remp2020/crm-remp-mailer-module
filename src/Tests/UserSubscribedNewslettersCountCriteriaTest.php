<?php

declare(strict_types=1);

namespace Crm\RempMailerModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\RempMailerModule\Models\Api\Client;
use Crm\RempMailerModule\Scenarios\UserSubscribedNewslettersCountCriteria;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

class UserSubscribedNewslettersCountCriteriaTest extends DatabaseTestCase
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

    public static function dataProvider(): array
    {
        return [
            'noSubscriptions_min2_shouldReturnFalse' => [
                'subscribedMailTypeIds' => [],
                'categoryMailTypeIds' => [1, 2, 3, 4, 5],
                'minCount' => 2,
                'expectedResult' => false,
            ],

            'oneSubscription_min2_shouldReturnFalse' => [
                'subscribedMailTypeIds' => [1],
                'categoryMailTypeIds' => [1, 2, 3, 4, 5],
                'minCount' => 2,
                'expectedResult' => false,
            ],

            'twoSubscriptions_min2_shouldReturnTrue' => [
                'subscribedMailTypeIds' => [1, 2],
                'categoryMailTypeIds' => [1, 2, 3, 4, 5],
                'minCount' => 2,
                'expectedResult' => true,
            ],

            'threeSubscriptions_min2_shouldReturnTrue' => [
                'subscribedMailTypeIds' => [1, 2, 3],
                'categoryMailTypeIds' => [1, 2, 3, 4, 5],
                'minCount' => 2,
                'expectedResult' => true,
            ],

            'oneSubscription_min1_shouldReturnTrue' => [
                'subscribedMailTypeIds' => [1],
                'categoryMailTypeIds' => [1, 2, 3],
                'minCount' => 1,
                'expectedResult' => true,
            ],

            'subscribedToOther_min2_shouldReturnFalse' => [
                'subscribedMailTypeIds' => [10, 11],
                'categoryMailTypeIds' => [1, 2, 3, 4, 5],
                'minCount' => 2,
                'expectedResult' => false,
            ],

            'mixedSubscriptions_min2_shouldReturnTrue' => [
                'subscribedMailTypeIds' => [1, 3, 10, 11],
                'categoryMailTypeIds' => [1, 2, 3, 4, 5],
                'minCount' => 2,
                'expectedResult' => true,
            ],

            'mixedSubscriptions_min3_shouldReturnFalse' => [
                'subscribedMailTypeIds' => [1, 3, 10, 11],
                'categoryMailTypeIds' => [1, 2, 3, 4, 5],
                'minCount' => 3,
                'expectedResult' => false,
            ],

            'twoSubscriptions_min5_shouldReturnFalse' => [
                'subscribedMailTypeIds' => [1, 2],
                'categoryMailTypeIds' => [1, 2, 3, 4, 5],
                'minCount' => 5,
                'expectedResult' => false,
            ],

            'fiveSubscriptions_min5_shouldReturnTrue' => [
                'subscribedMailTypeIds' => [1, 2, 3, 4, 5],
                'categoryMailTypeIds' => [1, 2, 3, 4, 5],
                'minCount' => 5,
                'expectedResult' => true,
            ],
        ];
    }

    #[DataProvider('dataProvider')]
    public function testMailTypeSubscribedCountCriteria(
        array $subscribedMailTypeIds,
        array $categoryMailTypeIds,
        int $minCount,
        bool $expectedResult,
    ): void {
        [$userRow, $userSelection] = $this->prepareUserData();

        $categoryCodes = ['category1', 'category2'];

        // Mock mail types from categories
        $mailTypes = [];
        foreach ($categoryMailTypeIds as $mailTypeId) {
            $mailType = (object)[
                'id' => $mailTypeId,
                'code' => "mail_type_{$mailTypeId}",
                'name' => "Mail Type {$mailTypeId}",
            ];
            $mailTypes[] = $mailType;
        }

        // Verify that getMailTypes receives the correct categoryCodes parameter
        $this->apiClient->shouldReceive('getMailTypes')
            ->with(null, $categoryCodes, null, false)
            ->andReturn($mailTypes)
            ->once();

        $rawPreferences = [];
        foreach ($subscribedMailTypeIds as $mailTypeId) {
            $rawPreferences[] = [
                'id' => $mailTypeId,
                'code' => "mail_type_{$mailTypeId}",
                'is_subscribed' => true,
                'variants' => [],
            ];
        }
        $this->apiClient->shouldReceive('getUserPreferences')
            ->andReturn($rawPreferences);

        $criteria = $this->inject(UserSubscribedNewslettersCountCriteria::class);

        $categoriesSelection = (object)['selection' => $categoryCodes];
        $countSelection = (object)['selection' => $minCount];
        $paramValues = [
            UserSubscribedNewslettersCountCriteria::CATEGORIES_KEY => $categoriesSelection,
            UserSubscribedNewslettersCountCriteria::COUNT_KEY => $countSelection,
        ];

        $result = $criteria->addConditions($userSelection, $paramValues, $userRow);
        $this->assertEquals($expectedResult, $result);
    }

    public function testNoCategoriesSelectedShouldCheckAllCategories(): void
    {
        [$userRow, $userSelection] = $this->prepareUserData();

        $mailTypes = [
            (object)['id' => 1, 'code' => 'mail_type_1', 'name' => 'Mail Type 1'],
            (object)['id' => 2, 'code' => 'mail_type_2', 'name' => 'Mail Type 2'],
            (object)['id' => 3, 'code' => 'mail_type_3', 'name' => 'Mail Type 3'],
        ];
        // When no categories are selected, getMailTypes should be called without categoryCodes parameter
        // (categoryCodes will be null)
        $this->apiClient->shouldReceive('getMailTypes')
            ->with(null, null, null, false)
            ->andReturn($mailTypes)
            ->once();

        $rawPreferences = [
            ['id' => 1, 'code' => 'mail_type_1', 'is_subscribed' => true, 'variants' => []],
            ['id' => 2, 'code' => 'mail_type_2', 'is_subscribed' => true, 'variants' => []],
        ];
        $this->apiClient->shouldReceive('getUserPreferences')
            ->andReturn($rawPreferences);

        $criteria = $this->inject(UserSubscribedNewslettersCountCriteria::class);

        $categoriesSelection = (object)['selection' => []];
        $countSelection = (object)['selection' => 2];
        $paramValues = [
            UserSubscribedNewslettersCountCriteria::CATEGORIES_KEY => $categoriesSelection,
            UserSubscribedNewslettersCountCriteria::COUNT_KEY => $countSelection,
        ];

        // Execute criteria - should return true (user has 2 subscriptions, minimum is 2)
        $result = $criteria->addConditions($userSelection, $paramValues, $userRow);

        $this->assertTrue($result);
    }

    public function testNoMailTypesInCategoryShouldReturnFalse(): void
    {
        [$userRow, $userSelection] = $this->prepareUserData();

        $categoryCodes = ['category1'];

        $this->apiClient->shouldReceive('getMailTypes')
            ->with(null, $categoryCodes, null, false)
            ->andReturn([])
            ->once();

        $criteria = $this->inject(UserSubscribedNewslettersCountCriteria::class);

        // Prepare param values
        $categoriesSelection = (object)['selection' => $categoryCodes];
        $countSelection = (object)['selection' => 2];
        $paramValues = [
            UserSubscribedNewslettersCountCriteria::CATEGORIES_KEY => $categoriesSelection,
            UserSubscribedNewslettersCountCriteria::COUNT_KEY => $countSelection,
        ];

        $result = $criteria->addConditions($userSelection, $paramValues, $userRow);

        $this->assertFalse($result);
    }

    public function testNoUserSubscriptionsShouldReturnFalse(): void
    {
        [$userRow, $userSelection] = $this->prepareUserData();

        $categoryCodes = ['category1'];

        $mailTypes = [
            (object)['id' => 1, 'code' => 'mail_type_1', 'name' => 'Mail Type 1'],
            (object)['id' => 2, 'code' => 'mail_type_2', 'name' => 'Mail Type 2'],
        ];
        $this->apiClient->shouldReceive('getMailTypes')
            ->with(null, $categoryCodes, null, false)
            ->andReturn($mailTypes)
            ->once();

        $this->apiClient->shouldReceive('getUserPreferences')
            ->andReturn([]);

        $criteria = $this->inject(UserSubscribedNewslettersCountCriteria::class);

        $categoriesSelection = (object)['selection' => $categoryCodes];
        $countSelection = (object)['selection' => 2];
        $paramValues = [
            UserSubscribedNewslettersCountCriteria::CATEGORIES_KEY => $categoriesSelection,
            UserSubscribedNewslettersCountCriteria::COUNT_KEY => $countSelection,
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
