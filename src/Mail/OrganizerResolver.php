<?php

namespace App\Mail;

use App\Entity\Choice;
use App\Entity\Contact;
use App\Entity\ContactList;
use App\Entity\Question;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;

/**
 * Seul endroit qui décide de l'expéditeur affiché et de l'adresse de réponse des emails :
 *  - ressource personnelle : « Prénom via Voteo », réponse à l'auteur ;
 *  - ressource d'organisation : « Organisation via Voteo », réponse à l'email de contact de l'organisation.
 * L'adresse d'expédition reste toujours celle du site (SPF / DKIM), seul le nom affiché change.
 */
final class OrganizerResolver
{
    public function __construct(
        #[Autowire('%app.transactional_mail_sender%')] private readonly string $senderAddress,
        #[Autowire('%app.transactional_mail_sender_friendlyname%')] private readonly string $siteName,
    ) {
    }

    public function for(Question|ContactList|Contact|Choice $subject): Organizer
    {
        $resource = match (true) {
            $subject instanceof Contact => $subject->getList(),
            $subject instanceof Choice => $subject->getQuestion(),
            default => $subject,
        };

        if (null !== $organization = $resource?->getOrganization()) {
            return new Organizer(
                name: (string) $organization->getName(),
                senderLabel: (string) $organization->getName(),
                replyToEmail: $organization->getContactEmail(),
                isOrganization: true,
                siteName: $this->siteName,
            );
        }

        $owner = $resource?->getOwner();
        if (null === $owner) {
            // Ressource personnelle sans auteur : ne devrait pas exister (supprimée avec le compte).
            return new Organizer($this->siteName, $this->siteName, null, false, $this->siteName);
        }

        return $this->forUser($owner);
    }

    /** Emails envoyés au nom d'une personne, hors ressource (ex. invitation à une organisation). */
    public function forUser(User $user): Organizer
    {
        return new Organizer(
            name: $user->getFullName(),
            senderLabel: (string) $user->getFirstname(),
            replyToEmail: $user->getEmail(),
            isOrganization: false,
            siteName: $this->siteName,
        );
    }

    /** Applique expéditeur et adresse de réponse à un email. */
    public function address(TemplatedEmail $email, Organizer $organizer): TemplatedEmail
    {
        // Address retire les retours à la ligne du nom : pas d'injection d'en-tête possible.
        $email->from(new Address($this->senderAddress, $organizer->getSenderName()));
        if (null !== $organizer->replyToEmail && '' !== $organizer->replyToEmail) {
            $email->replyTo(new Address($organizer->replyToEmail, $organizer->name));
        }

        return $email;
    }
}
