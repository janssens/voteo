<?php

namespace App\Organization;

use App\Entity\ContactList;
use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\Question;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Règles de gestion des organisations : invitations, départ d'un membre, suppression.
 */
final class OrganizationManager
{
    /** Garde-fou contre l'envoi massif d'invitations depuis une organisation. */
    public const MAX_PENDING_INVITATIONS = 50;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvitationMailer $invitationMailer,
    ) {
    }

    /**
     * Invite une adresse email. S'il existe déjà une invitation pour cette adresse, elle est renouvelée
     * (nouveau lien, nouvelle échéance) au lieu d'en créer une seconde.
     *
     * @throws InvitationException
     */
    public function invite(Organization $organization, string $email, User $inviter): Invitation
    {
        $email = mb_strtolower(trim($email));

        foreach ($organization->getMemberships() as $membership) {
            if (mb_strtolower((string) $membership->getUser()?->getEmail()) === $email) {
                throw new InvitationException(sprintf('%s fait déjà partie de l\'organisation.', $membership->getUser()->getFullName()));
            }
        }

        $invitation = null;
        foreach ($organization->getInvitations() as $existing) {
            if ($existing->getEmail() === $email) {
                $invitation = $existing;
                break;
            }
        }

        if (null === $invitation) {
            if ($organization->getInvitations()->count() >= self::MAX_PENDING_INVITATIONS) {
                throw new InvitationException(sprintf('Cette organisation a déjà %d invitations en attente : annulez-en avant d\'en envoyer de nouvelles.', self::MAX_PENDING_INVITATIONS));
            }
            $invitation = new Invitation($organization, $email, $inviter);
            $organization->getInvitations()->add($invitation);
            $this->em->persist($invitation);
        }

        $this->sendInvitation($invitation, $inviter);

        return $invitation;
    }

    /** Renvoie une invitation : nouveau lien (l'ancien ne fonctionne plus) et nouvelle échéance. */
    public function sendInvitation(Invitation $invitation, User $sender): void
    {
        $token = $invitation->renewToken();
        $this->em->flush();

        $this->invitationMailer->send($invitation, $token, $sender);
    }

    public function cancelInvitation(Invitation $invitation): void
    {
        $invitation->getOrganization()?->getInvitations()->removeElement($invitation);
        $this->em->remove($invitation);
        $this->em->flush();
    }

    /**
     * Fait entrer $user dans l'organisation de l'invitation (lien valable pour le compte qui l'ouvre,
     * quelle que soit son adresse) et consomme l'invitation.
     *
     * @throws InvitationException si l'invitation a expiré
     */
    public function accept(Invitation $invitation, User $user): Organization
    {
        if ($invitation->isExpired()) {
            throw new InvitationException('Cette invitation a expiré. Demandez à un membre de l\'organisation de vous la renvoyer.');
        }

        $organization = $invitation->getOrganization();
        $organization->addMember($user, $invitation->getInvitedBy());
        $this->cancelInvitation($invitation);

        return $organization;
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
