<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class ApiKeyUser implements UserInterface
{
    private string $apiKeyIdentifier;
    private array $roles;

    public function __construct(string $apiKeyIdentifier, array $roles)
    {
        $this->apiKeyIdentifier = $apiKeyIdentifier;
        $this->roles = $roles;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getUserIdentifier(): string
    {
        return $this->apiKeyIdentifier;
    }

    public function eraseCredentials(): void
    {
        // Není potřeba nic dělat
    }
} 