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
 * Restreint l'accès aux données d'un organisateur : questions, choix, listes et contacts.
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

        $owner = match (true) {
            $subject instanceof Question, $subject instanceof ContactList => $subject->getOwner(),
            $subject instanceof Choice => $subject->getQuestion()?->getOwner(),
            $subject instanceof Contact => $subject->getList()?->getOwner(),
        };

        return null !== $owner && $owner->getId() === $user->getId();
    }
}
