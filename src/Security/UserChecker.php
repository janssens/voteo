<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuse toute authentification (formulaire, remember-me, Security::login()) tant que
 * l'adresse email du compte n'a pas été confirmée via le lien reçu par email.
 */
class UserChecker implements UserCheckerInterface
{
    public const UNVERIFIED_MESSAGE = 'Votre adresse email n\'est pas encore confirmée. Cliquez sur le lien reçu par email pour activer votre compte.';

    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(self::UNVERIFIED_MESSAGE);
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
