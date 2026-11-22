<?php

namespace App\Mail;

/**
 * Au nom de qui un email part et à qui ses réponses arrivent :
 * l'auteur (ressource personnelle) ou l'organisation.
 */
final class Organizer
{
    public function __construct(
        /** Nom affiché dans les pages et pieds d'email : nom complet de l'auteur, ou nom de l'organisation. */
        public readonly string $name,
        /** Partie gauche de l'expéditeur « X via Voteo » : prénom de l'auteur, ou nom de l'organisation. */
        public readonly string $senderLabel,
        /** Adresse de réponse : email de l'auteur, ou email de contact de l'organisation (null si inconnue). */
        public readonly ?string $replyToEmail,
        public readonly bool $isOrganization,
        private readonly string $siteName,
    ) {
    }

    /** Nom d'expéditeur : « Gaëtan via Voteo », « Asso Jardins partagés via Voteo ». */
    public function getSenderName(): string
    {
        return sprintf('%s via %s', $this->senderLabel, $this->siteName);
    }
}
