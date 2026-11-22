<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountFormType;
use App\Privacy\PersonalData;
use App\Repository\ContactListRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace « Mon compte » de l'organisateur : rectification, export et suppression de ses données (RGPD).
 */
#[IsGranted('IS_AUTHENTICATED')]
#[Route('/compte')]
final class AccountController extends AbstractController
{
    #[Route('', name: 'account', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        ContactListRepository $lists,
        EmailVerifier $emailVerifier,
        #[Autowire('%app.transactional_mail_sender%')] string $sender,
        #[Autowire('%app.transactional_mail_sender_friendlyname%')] string $senderName,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $previousEmail = $user->getEmail();

        $form = $this->createForm(AccountFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $emailChanged = 0 !== strcasecmp((string) $previousEmail, (string) $user->getEmail());
            if ($emailChanged) {
                $user->setIsVerified(false);
            }
            $em->flush();

            if ($emailChanged) {
                $emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                    (new TemplatedEmail())
                        ->from(new Address($sender, $senderName))
                        ->to((string) $user->getEmail())
                        ->subject('Confirmez votre adresse email')
                        ->htmlTemplate('registration/confirmation_email.html.twig')
                );
                $this->addFlash('success', 'Vos informations ont été mises à jour. Un email de confirmation a été envoyé à votre nouvelle adresse.');
            } else {
                $this->addFlash('success', 'Vos informations ont été mises à jour.');
            }

            return $this->redirectToRoute('account');
        }

        $userLists = $lists->findBy(['owner' => $user]);

        return $this->render('account/index.html.twig', [
            'form' => $form,
            'nb_lists' => count($userLists),
            'nb_contacts' => array_sum(array_map(static fn ($l) => $l->getContacts()->count(), $userLists)),
            'nb_questions' => $user->getQuestions()->count(),
        ]);
    }

    #[Route('/export', name: 'account_export', methods: ['GET'])]
    public function export(PersonalData $personalData): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $response = new JsonResponse($personalData->exportUser($user));
        $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('voteo-mes-donnees-%s.json', date('Y-m-d')),
        ));

        return $response;
    }

    #[Route('/supprimer', name: 'account_delete', methods: ['POST'])]
    public function delete(Request $request, PersonalData $personalData, UserPasswordHasherInterface $hasher, Security $security): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('account_delete', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('account');
        }
        if (!$hasher->isPasswordValid($user, (string) $request->request->get('password'))) {
            $this->addFlash('error', 'Mot de passe incorrect : votre compte n\'a pas été supprimé.');

            return $this->redirectToRoute('account');
        }

        $personalData->deleteUser($user);
        $security->logout(false);
        $this->addFlash('success', 'Votre compte et toutes vos données ont été définitivement supprimés.');

        return $this->redirectToRoute('home');
    }
}
