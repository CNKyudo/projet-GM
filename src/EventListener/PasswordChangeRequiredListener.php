<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Redirige tout utilisateur ayant mustChangePassword = true vers la page de changement forcé.
 *
 * Les routes suivantes sont exemptées pour éviter les boucles de redirection :
 *   - user_force_change_password (page cible)
 *   - app_login
 *   - app_logout
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
final readonly class PasswordChangeRequiredListener
{
    /** @var list<string> Routes exemptées de la redirection */
    private const array EXCLUDED_ROUTES = [
        'user_force_change_password',
        'app_login',
        'app_logout',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RouterInterface $router,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token instanceof \Symfony\Component\Security\Core\Authentication\Token\TokenInterface) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isMustChangePassword()) {
            return;
        }

        $currentRoute = $event->getRequest()->attributes->get('_route');
        if (\in_array($currentRoute, self::EXCLUDED_ROUTES, true)) {
            return;
        }

        $url = $this->router->generate('user_force_change_password');
        $event->setResponse(new RedirectResponse($url));
    }
}
