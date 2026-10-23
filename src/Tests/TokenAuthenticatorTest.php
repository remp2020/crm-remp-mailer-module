<?php

declare(strict_types=1);

namespace Crm\RempMailerModule\Tests;

use Crm\ApplicationModule\Models\Event\LazyEventEmitter;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\RempMailerModule\Models\Api\Client;
use Crm\RempMailerModule\Models\Authenticator\TokenAuthenticator;
use Crm\UsersModule\Events\LoginAttemptEvent;
use Crm\UsersModule\Events\LoginAttemptHandler;
use Crm\UsersModule\Repositories\LoginAttemptsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;

class TokenAuthenticatorTest extends DatabaseTestCase
{
    private UsersRepository $usersRepository;

    private LoginAttemptsRepository $loginAttemptsRepository;

    private LazyEventEmitter $lazyEventEmitter;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            LoginAttemptsRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->loginAttemptsRepository = $this->getRepository(LoginAttemptsRepository::class);

        $this->lazyEventEmitter = $this->inject(LazyEventEmitter::class);
        $this->lazyEventEmitter->addListener(
            LoginAttemptEvent::class,
            $this->inject(LoginAttemptHandler::class),
        );
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        $this->lazyEventEmitter->removeAllListeners(LoginAttemptEvent::class);

        parent::tearDown();
    }

    public function testMailTokenAdmin()
    {
        $admin = $this->usersRepository->add('admin@example.com', 'password', UsersRepository::ROLE_ADMIN);
        $client = \Mockery::mock(Client::class)
            ->shouldReceive('checkAutologinToken')
            ->with('mail-token')
            ->andReturn($admin->email)
            ->getMock();
        $authenticator = $this->container->createInstance(TokenAuthenticator::class, ['apiClient' => $client])
            ->setCredentials(['mailToken' => 'mail-token']);

        try {
            $authenticator->authenticate();
            $this->fail('Admin login with mail token should be rejected.');
        } catch (AuthenticationException $e) {
            $this->assertEquals('Autologin for this account is disabled', $e->getMessage());
            $this->assertEquals(Authenticator::IdentityNotFound, $e->getCode());
        }

        $lastAttempt = $this->lastLoginAttempt();
        $this->assertEquals(LoginAttemptsRepository::STATUS_LOGIN_TYPE_DISABLED_FOR_ADMIN, $lastAttempt->status);
        $this->assertEquals($admin->id, $lastAttempt->user_id);
    }

    private function lastLoginAttempt(): ActiveRow
    {
        return $this->loginAttemptsRepository->getTable()
            ->order('id DESC')
            ->limit(1)
            ->fetch();
    }
}
