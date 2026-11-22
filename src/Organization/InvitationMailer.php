<?php

namespace App\Organization;

use App\Entity\Invitation;
use App\Entity\User;
use App\Mail\OrganizerResolver;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Email d'invitation à rejoindre une organisation.
 * Expéditeur affiché : « Prénom via Voteo », réponse à la personne qui invite (voir OrganizerResolver).
 */
final class InvitationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly OrganizerResolver $organizers,
        #[Autowire('%app.transactional_mail_sender_friendlyname%')] private readonly string $siteName,
    ) {
    }

    public function send(Invitation $invitation, string $token, User $inviter): void
    {
        $organization = $invitation->getOrganization();

        // Envoyée au nom de la personne qui invite (pas de l'organisation) : c'est à elle qu'on répond.
        $email = $this->organizers->address(new TemplatedEmail(), $this->organizers->forUser($inviter))
            ->to((string) $invitation->getEmail())
            ->subject(sprintf('%s vous invite à rejoindre « %s » sur %s', $inviter->getFirstname(), $organization->getName(), $this->siteName))
            ->htmlTemplate('emails/invitation.html.twig')
            ->textTemplate('emails/invitation.txt.twig')
            ->context([
                'invitation' => $invitation,
                'organization' => $organization,
                'inviter' => $inviter,
                'site_name' => $this->siteName,
                'invitation_url' => $this->urlGenerator->generate('invitation_show', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        $this->mailer->send($email);
    }
}
