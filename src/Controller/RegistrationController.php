<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            // preuve du consentement à la politique de confidentialité (case obligatoire)
            $user->setPrivacyAcceptedAt(new \DateTimeImmutable());

            $entityManager->persist($user);
            $entityManager->flush();

            $this->emailVerifier->sendConfirmation($user);

            // Pas de connexion automatique : le compte n'est utilisable qu'une fois l'adresse confirmée (UserChecker)
            $this->addFlash('success', sprintf('Votre compte est créé. Un email de confirmation a été envoyé à %s : cliquez sur le lien qu\'il contient pour activer votre compte, puis connectez-vous.', $user->getEmail()));

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    /**
     * Lien de confirmation reçu par email. Il identifie le compte par son id (URL signée),
     * sans exiger d'être connecté.
     */
    #[Route('/verify/email', name: EmailVerifier::VERIFY_ROUTE)]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, UserRepository $users): Response
    {
        $user = $users->find($request->query->getInt('id'));
        if (!$user) {
            $this->addFlash('verify_email_error', 'Ce lien de confirmation n\'est pas valide.');

            return $this->redirectToRoute('app_verify_resend');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_verify_resend');
        }

        $this->addFlash('success', 'Votre adresse email a bien été confirmée. Merci !');

        // Changement d'adresse depuis « Mon compte » : la session en cours est conservée
        return $this->getUser() ? $this->redirectToRoute('account') : $this->redirectToRoute('app_login');
    }

    /**
     * Renvoi du lien de confirmation (lien expiré ou email perdu).
     * La réponse est la même que l'adresse corresponde ou non à un compte, pour ne pas révéler les inscrits.
     */
    #[Route('/verify/resend', name: 'app_verify_resend', methods: ['GET', 'POST'])]
    public function resend(Request $request, UserRepository $users): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('verify_resend', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

                return $this->redirectToRoute('app_verify_resend');
            }

            $email = trim((string) $request->request->get('email'));
            if ('' !== $email) {
                foreach ($users->findBy(['email' => $email, 'isVerified' => false]) as $user) {
                    $this->emailVerifier->sendConfirmation($user);
                }
            }

            $this->addFlash('success', 'Si un compte non confirmé correspond à cette adresse, un nouveau lien vient de lui être envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/resend.html.twig');
    }
}
