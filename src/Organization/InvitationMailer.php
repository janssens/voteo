<?php

namespace App\Organization;

use App\Entity\Invitation;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Email d'invitation à rejoindre une organisation.
 * Expéditeur affiché : « Prénom via Voteo » (adresse technique du site), réponse à la personne qui invite.
 */
final class InvitationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%app.transactional_mail_sender%')] private readonly string $senderAddress,
        #[Autowire('%app.transactional_mail_sender_friendlyname%')] private readonly string $siteName,
    ) {
    }

    public function send(Invitation $invitation, string $token, User $inviter): void
    {
        $organization = $invitation->getOrganization();

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderAddress, sprintf('%s via %s', $inviter->getFirstname(), $this->siteName)))
            ->replyTo(new Address((string) $inviter->getEmail(), $inviter->getFullName()))
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
