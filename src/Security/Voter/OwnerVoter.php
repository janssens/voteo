<?php

namespace App\Security\Voter;

use App\Entity\Choice;
use App\Entity\Contact;
use App\Entity\ContactList;
use App\Entity\Question;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Restreint l'accès aux questions, choix, listes et contacts :
 *  - ressource d'une organisation : tout membre de l'organisation ;
 *  - ressource personnelle : son auteur uniquement ;
 *  - ROLE_ADMIN : tout.
 * Utilisé comme "entity permission" par les CRUD EasyAdmin, et dans les actions personnalisées.
 *
 * @extends Voter<string, Question|Choice|ContactList|Contact|null>
 */
final class OwnerVoter extends Voter
{
    public const OWNER = 'OWNER';

    public function __construct(private readonly AccessDecisionManagerInterface $accessDecisionManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::OWNER === $attribute
            && (null === $subject
                || $subject instanceof Question
                || $subject instanceof Choice
                || $subject instanceof ContactList
                || $subject instanceof Contact);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Entité en cours de création (formulaire "new" ou entrée de collection) : rien à protéger.
        if (null === $subject || null === $subject->getId()) {
            return true;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        // Ressource racine qui porte l'organisation et l'auteur.
        $resource = match (true) {
            $subject instanceof Question, $subject instanceof ContactList => $subject,
            $subject instanceof Choice => $subject->getQuestion(),
            $subject instanceof Contact => $subject->getList(),
        };
        if (null === $resource) {
            return false;
        }

        if (null !== $organization = $resource->getOrganization()) {
            return $organization->hasMember($user);
        }

        return $resource->getOwner()?->getId() === $user->getId();
    }
}
