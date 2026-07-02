<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Gère la redirection après une authentification réussie.
 *
 * Si l'utilisateur a mustChangePassword = true, il est redirigé vers la page
 * de changement de mot de passe obligatoire (/profile/password/force).
 *
 * Sinon, il est redirigé vers la page demandée ou vers l'accueil.
 */
final readonly class LoginAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private RouterInterface $router,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user = $token->getUser();

        if ($user instanceof User && $user->isMustChangePassword()) {
            return new RedirectResponse(
                $this->router->generate('user_force_change_password')
            );
        }

        $targetPath = $request->getSession()->get('_security.main.target_path');
        if ($targetPath) {
            $request->getSession()->remove('_security.main.target_path');

            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->router->generate('app_home'));
    }
}
