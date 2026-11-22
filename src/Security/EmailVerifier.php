<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class EmailVerifier
{
    public const VERIFY_ROUTE = 'app_verify_email';

    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        #[Autowire('%app.transactional_mail_sender%')] private string $sender,
        #[Autowire('%app.transactional_mail_sender_friendlyname%')] private string $senderName,
    ) {
    }

    /**
     * Envoie le lien de confirmation (inscription, renvoi, changement d'adresse).
     * Le lien porte l'id du compte : il fonctionne sans être connecté, la connexion étant
     * refusée tant que l'adresse n'est pas confirmée (voir UserChecker).
     */
    public function sendConfirmation(User $user): void
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            self::VERIFY_ROUTE,
            (string) $user->getId(),
            (string) $user->getEmail(),
            ['id' => $user->getId()],
        );

        $email = (new TemplatedEmail())
            ->from(new Address($this->sender, $this->senderName))
            ->to((string) $user->getEmail())
            ->subject('Confirmez votre adresse email')
            ->htmlTemplate('registration/confirmation_email.html.twig')
            ->context([
                'signedUrl' => $signature->getSignedUrl(),
                'expiresAtMessageKey' => $signature->getExpirationMessageKey(),
                'expiresAtMessageData' => $signature->getExpirationMessageData(),
            ]);

        $this->mailer->send($email);
    }

    /**
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmation(Request $request, User $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), (string) $user->getEmail());

        $user->setIsVerified(true);

        $this->entityManager->flush();
    }
}
