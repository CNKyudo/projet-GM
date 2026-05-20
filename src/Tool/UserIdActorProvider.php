<?php

declare(strict_types=1);

namespace App\Tool;

use Gedmo\Tool\ActorProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class UserIdActorProvider implements ActorProviderInterface
{
    public function __construct(
        private ?TokenStorageInterface $tokenStorage = null,
        private ?AuthorizationCheckerInterface $authorizationChecker = null,
    ) {
    }

    public function getActor(): ?string
    {
        if (!$this->tokenStorage instanceof TokenStorageInterface || !$this->authorizationChecker instanceof AuthorizationCheckerInterface) {
            return null;
        }

        $token = $this->tokenStorage->getToken();

        if (!$token instanceof \Symfony\Component\Security\Core\Authentication\Token\TokenInterface || !$this->authorizationChecker->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return null;
        }

        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return null;
        }

        if (method_exists($user, 'getId')) {
            return (string) $user->getId();
        }

        return null;
    }
}
