<?php

declare(strict_types=1);

namespace Crm\RempMailerModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\RempMailerModule\Models\Api\Client;
use Crm\RempMailerModule\Scenarios\MailReceivedCriteria;
use Crm\ScenariosModule\Events\ConditionCheckException;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Mockery;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;

class MailReceivedCriteriaTest extends DatabaseTestCase
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

    public function testEmptyTimeframeChecksWholeDeliveryHistory(): void
    {
        [$userRow, $userSelection] = $this->prepareUserData();

        $this->apiClient->shouldReceive('getMailLogs')
            ->withArgs(function (?string $email, ?array $filter) use ($userRow) {
                return $email === $userRow->email && $filter === null;
            })
            ->andReturn([(object) ['id' => 1]]);

        $result = $this->evaluateCriteria($userSelection, $userRow, (object) []);
        $this->assertTrue($result);
    }

    public function testEmptyTimeframeWithNoReceivedMailReturnsFalse(): void
    {
        [$userRow, $userSelection] = $this->prepareUserData();

        $this->apiClient->shouldReceive('getMailLogs')
            ->andReturn([]);

        $result = $this->evaluateCriteria($userSelection, $userRow, (object) []);
        $this->assertFalse($result);
    }

    public function testIncompleteTimeframeThrows(): void
    {
        [$userRow, $userSelection] = $this->prepareUserData();

        $this->apiClient->shouldNotReceive('getMailLogs');
        $this->expectException(ConditionCheckException::class);

        $timeframeValues = (object) [
            'operator' => MailReceivedCriteria::OPERATOR_BEFORE,
            'unit' => 'days',
        ];
        $this->evaluateCriteria($userSelection, $userRow, $timeframeValues);
    }

    public static function timeframeOperatorDataProvider(): array
    {
        return [
            'Before_FiltersDeliveredAtTo' => [
                'operator' => MailReceivedCriteria::OPERATOR_BEFORE,
                'expectedFilterBound' => 'to',
            ],
            'InTheLast_FiltersDeliveredAtFrom' => [
                'operator' => MailReceivedCriteria::OPERATOR_IN_THE_LAST,
                'expectedFilterBound' => 'from',
            ],
        ];
    }

    #[DataProvider('timeframeOperatorDataProvider')]
    public function testTimeframeOperatorMapsToDeliveredAtFilterBound(string $operator, string $expectedFilterBound): void
    {
        [$userRow, $userSelection] = $this->prepareUserData();

        $this->apiClient->shouldReceive('getMailLogs')
            ->withArgs(function (?string $email, ?array $filter) use ($expectedFilterBound) {
                return array_keys($filter['delivered_at']) === [$expectedFilterBound]
                    && $filter['delivered_at'][$expectedFilterBound] instanceof DateTime;
            })
            ->andReturn([(object) ['id' => 1]]);

        $timeframeValues = (object) [
            'operator' => $operator,
            'unit' => 'days',
            'selection' => 7,
        ];
        $result = $this->evaluateCriteria($userSelection, $userRow, $timeframeValues);
        $this->assertTrue($result);
    }

    private function evaluateCriteria(Selection $userSelection, ActiveRow $userRow, object $timeframeValues): bool
    {
        /** @var MailReceivedCriteria $criteria */
        $criteria = $this->inject(MailReceivedCriteria::class);

        $paramValues = [
            MailReceivedCriteria::TEMPLATE_KEY => (object) ['selection' => ['welcome_email']],
            MailReceivedCriteria::TIMEFRAME_KEY => $timeframeValues,
        ];

        return $criteria->addConditions($userSelection, $paramValues, $userRow);
    }

    private function prepareUserData(): array
    {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('user@example.com');

        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->inject(UsersRepository::class);

        $userSelection = $usersRepository->getTable()
            ->where(['users.id' => $userRow->id]);

        return [$userRow, $userSelection];
    }
}
