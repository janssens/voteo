<?php

namespace App\Organization;

use App\Entity\ContactList;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\Question;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Règles de gestion des organisations : départ d'un membre, suppression.
 */
final class OrganizationManager
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Le dernier membre ne peut pas partir : l'organisation se retrouverait sans personne pour la gérer.
     */
    public function canRemove(Membership $membership): bool
    {
        return $membership->getOrganization()?->getMemberships()->count() > 1;
    }

    /**
     * Retire un membre (ou le membre lui-même s'il quitte l'organisation).
     * Ses listes et questions restent à l'organisation.
     *
     * @throws LastMemberException
     */
    public function removeMember(Membership $membership): void
    {
        if (!$this->canRemove($membership)) {
            throw new LastMemberException($membership->getOrganization());
        }

        $organization = $membership->getOrganization();
        $user = $membership->getUser();

        $organization->removeMembership($membership);
        if ($user?->getLastOrganization() === $organization) {
            $user->setLastOrganization(null);
        }

        $this->em->flush();
    }

    /**
     * Supprime l'organisation et tout son contenu : listes, contacts, questions, choix et réponses.
     *
     * Passe par Doctrine plutôt que par la cascade SQL : les réponses, choix et contacts
     * n'ont pas de ON DELETE CASCADE en base.
     */
    public function delete(Organization $organization): void
    {
        foreach ($this->em->getRepository(Question::class)->findBy(['organization' => $organization]) as $question) {
            $this->em->remove($question);
        }
        foreach ($this->em->getRepository(ContactList::class)->findBy(['organization' => $organization]) as $list) {
            $this->em->remove($list);
        }

        // Personne ne doit retrouver cette organisation comme dernier contexte.
        $this->em->createQueryBuilder()
            ->update(User::class, 'u')
            ->set('u.lastOrganization', ':none')
            ->where('u.lastOrganization = :organization')
            ->setParameter('none', null)
            ->setParameter('organization', $organization)
            ->getQuery()
            ->execute();
        foreach ($organization->getMemberships() as $membership) {
            if ($membership->getUser()?->getLastOrganization() === $organization) {
                $membership->getUser()->setLastOrganization(null);
            }
            $membership->getUser()?->getMemberships()->removeElement($membership);
        }

        $this->em->remove($organization);
        $this->em->flush();
    }
}
