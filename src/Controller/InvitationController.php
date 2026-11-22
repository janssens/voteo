<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\User;
use App\Organization\CurrentContext;
use App\Organization\InvitationException;
use App\Organization\OrganizationManager;
use App\Repository\InvitationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Lien reçu par email pour rejoindre une organisation.
 *
 * Sans compte ou non connecté : la page propose de se connecter ou de s'inscrire, et mémorise le lien
 * pour y revenir après la connexion. Le lien vaut pour le compte qui l'ouvre, quelle que soit son adresse.
 */
#[Route('/invitation/{token}', requirements: ['token' => '[A-Za-z0-9_-]{20,100}'])]
final class InvitationController extends AbstractController
{
    use TargetPathTrait;

    /** Adresse invitée, pour pré-remplir le formulaire d'inscription. */
    public const SESSION_EMAIL = 'app.invitation_email';

    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly OrganizationManager $manager,
        private readonly CurrentContext $currentContext,
    ) {
    }

    #[Route('', name: 'invitation_show', methods: ['GET'])]
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->findInvitation($token);
        if (null === $invitation || $invitation->isExpired()) {
            return $this->render('invitation/show.html.twig', [
                'state' => null === $invitation ? 'invalid' : 'expired',
                'invitation' => $invitation,
            ], new Response(status: null === $invitation ? Response::HTTP_NOT_FOUND : Response::HTTP_GONE));
        }

        if (!$this->getUser() instanceof User) {
            $session = $request->getSession();
            $this->saveTargetPath($session, 'main', $request->getUri());
            $session->set(self::SESSION_EMAIL, $invitation->getEmail());

            return $this->render('invitation/show.html.twig', [
                'state' => 'anonymous',
                'invitation' => $invitation,
            ]);
        }

        return $this->render('invitation/show.html.twig', [
            'state' => 'confirm',
            'invitation' => $invitation,
            'token' => $token,
            'already_member' => $invitation->getOrganization()->hasMember($this->getUser()),
        ]);
    }

    #[Route('/accepter', name: 'invitation_accept', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function accept(Request $request, string $token): Response
    {
        if (null === $invitation = $this->findPostedInvitation($request, $token, 'invitation_accept')) {
            return $this->redirectToRoute('invitation_show', ['token' => $token]);
        }

        /** @var User $user */
        $user = $this->getUser();
        try {
            $organization = $this->manager->accept($invitation, $user);
        } catch (InvitationException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('invitation_show', ['token' => $token]);
        }

        $request->getSession()->remove(self::SESSION_EMAIL);
        $this->currentContext->switchTo($organization);
        $this->addFlash('success', sprintf('Bienvenue dans « %s » ! Vous travaillez maintenant dans cette organisation : changez d\'espace avec le sélecteur en haut du menu.', $organization->getName()));

        return $this->redirectToRoute('admin_organization_show');
    }

    #[Route('/refuser', name: 'invitation_decline', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function decline(Request $request, string $token): Response
    {
        if (null === $invitation = $this->findPostedInvitation($request, $token, 'invitation_decline')) {
            return $this->redirectToRoute('invitation_show', ['token' => $token]);
        }

        $name = $invitation->getOrganization()->getName();
        $this->manager->cancelInvitation($invitation);
        $request->getSession()->remove(self::SESSION_EMAIL);
        $this->addFlash('notice', sprintf('Vous avez refusé l\'invitation à rejoindre « %s ».', $name));

        return $this->redirectToRoute('home');
    }

    private function findInvitation(string $token): ?Invitation
    {
        return $this->invitations->findOneBy(['tokenHash' => Invitation::hashToken($token)]);
    }

    /** Null (avec un message) si le jeton CSRF est invalide ; 404 si l'invitation n'existe plus. */
    private function findPostedInvitation(Request $request, string $token, string $csrfId): ?Invitation
    {
        if (!$this->isCsrfTokenValid($csrfId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return null;
        }

        return $this->findInvitation($token) ?? throw $this->createNotFoundException();
    }
}
