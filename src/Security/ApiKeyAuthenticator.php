<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Psr\Log\LoggerInterface;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    private string $apiKey;
    private ?LoggerInterface $logger;

    public function __construct(string $apiKey, LoggerInterface $logger = null)
    {
        $this->apiKey = $apiKey;
        $this->logger = $logger;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $authorizationHeader = $request->headers->get('Authorization');
        
        if ($this->logger) {
            $this->logger->info('Received authorization header: ' . $authorizationHeader);
        }
        
        // Extrakt API klíče z hlavičky - podporujeme oba formáty
        $apiKey = $authorizationHeader;
        
        // Pokud začíná "Bearer ", odstraníme tento prefix
        if (str_starts_with($authorizationHeader, 'Bearer ')) {
            $apiKey = substr($authorizationHeader, 7);
        }
        
        if ($this->logger) {
            $this->logger->info('Extracted API key: ' . $apiKey);
        }

        if ($apiKey !== $this->apiKey) {
            throw new CustomUserMessageAuthenticationException('Neplatný API klíč');
        }

        // Vytvoříme identitu s rolí admin
        return new SelfValidatingPassport(
            new UserBadge('admin_api', function () {
                return new ApiKeyUser('admin_api', ['ROLE_ADMIN']);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Pokračovat ve zpracování požadavku
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'message' => 'Autentizace selhala: ' . $exception->getMessage()
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
} 