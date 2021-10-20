<?php

namespace Crm\RempMailerModule\Models\Authenticator;

use Crm\ApplicationModule\Authenticator\AuthenticatorInterface;
use Crm\ApplicationModule\Authenticator\BaseAuthenticator;
use Crm\RempMailerModule\Models\Api\Client;
use Crm\UsersModule\Auth\UserAuthenticator;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Request;
use Nette\Security\AuthenticationException;

/**
 * TokenAuthenticator authenticates user based on mailToken.
 *
 * Required credentials (use setCredentials()):
 *
 * - 'mailToken'
 *
 */
class TokenAuthenticator extends BaseAuthenticator
{
    private $userManager;

    private $apiClient;

    /** @var string */
    private $mailToken = null;

    public function __construct(
        Emitter $emitter,
        \Tomaj\Hermes\Emitter $hermesEmitter,
        Request $request,
        UserManager $userManager,
        Client $apiClient
    ) {
        parent::__construct($emitter, $hermesEmitter, $request);

        $this->userManager = $userManager;
        $this->apiClient = $apiClient;
    }

    public function authenticate()
    {
        if ($this->mailToken !== null) {
            return $this->process();
        }
        return false;
    }

    public function setCredentials(array $credentials) : AuthenticatorInterface
    {
        parent::setCredentials($credentials);

        $this->mailToken = $credentials['mailToken'] ?? null;

        return $this;
    }

    /**
     * @throws AuthenticationException
     */
    private function process() : ActiveRow
    {
        $email = $this->apiClient->checkAutologinToken($this->mailToken);
        if (!$email) {
            throw new AuthenticationException('Autologin was not successful, please try to log in with email and password', UserAuthenticator::IDENTITY_NOT_FOUND);
        }

        $user = $this->userManager->loadUserByEmail($email);
        if (!$user) {
            $this->addAttempt($email, null, $this->source, LoginAttemptsRepository::STATUS_TOKEN_NOT_FOUND);
            throw new AuthenticationException('Invalid token provided', UserAuthenticator::IDENTITY_NOT_FOUND);
        }
        if ($user->role === UsersRepository::ROLE_ADMIN) {
            throw new AuthenticationException('Autologin for this account is disabled', UserAuthenticator::IDENTITY_NOT_FOUND);
        }

        $this->addAttempt($user->email, $user, $this->source, LoginAttemptsRepository::STATUS_TOKEN_OK);
        return $user;
    }
}
