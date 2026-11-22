<?php

namespace App\Controller\Admin;

use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\Organization;
use App\Entity\User;
use App\Form\OrganizationType;
use App\Organization\CurrentContext;
use App\Organization\InvitationException;
use App\Organization\LastMemberException;
use App\Organization\OrganizationManager;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Sélecteur global de contexte (personnel / organisation) et gestion de l'organisation active.
 *
 * Les pages de gestion portent toujours sur l'organisation du contexte actif (pas d'id dans l'URL) :
 * CurrentContext garantit que l'utilisateur en est membre, et tous les membres ont les mêmes droits.
 */
#[IsGranted('ROLE_USER')]
final class OrganizationController extends AbstractController
{
    public function __construct(
        private readonly CurrentContext $currentContext,
        private readonly EntityManagerInterface $em,
        private readonly OrganizationManager $manager,
    ) {
    }

    /**
     * Valeur reçue du sélecteur : "perso", l'id d'une organisation, ou "new" (créer une organisation).
     * Ramène toujours au tableau de bord, pour ne pas rester sur une fiche de l'ancien contexte.
     */
    #[AdminRoute(path: '/contexte', name: 'context_switch', options: ['methods' => ['POST']])]
    public function switchContext(Request $request, OrganizationRepository $organizations): Response
    {
        if (!$this->isCsrfTokenValid('context_switch', (string) $request->request->get('_token'))) {
            return $this->expired();
        }

        $value = (string) $request->request->get('context');

        if ('new' === $value) {
            return $this->redirectToRoute('admin_organization_new');
        }

        $organization = 'perso' === $value ? null : $organizations->find((int) $value);
        if ('perso' !== $value && null === $organization) {
            throw $this->createNotFoundException();
        }

        try {
            $this->currentContext->switchTo($organization);
        } catch (\InvalidArgumentException) {
            throw $this->createAccessDeniedException();
        }

        return $this->redirectToRoute('admin');
    }

    #[AdminRoute(path: '/organisation/nouvelle', name: 'organization_new', options: ['methods' => ['GET', 'POST']])]
    public function new(Request $request): Response
    {
        $user = $this->getAppUser();

        $organization = (new Organization())->setContactEmail((string) $user->getEmail());
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $organization->addMember($user);
            $this->em->persist($organization);
            $this->em->flush();

            $this->currentContext->switchTo($organization);
            $this->addFlash('success', sprintf('L\'organisation « %s » est créée. Les listes et questions que vous créez maintenant lui appartiennent.', $organization->getName()));

            return $this->redirectToRoute('admin_organization_show');
        }

        return $this->render('admin/organization/form.html.twig', [
            'form' => $form,
            'organization' => null,
        ]);
    }

    /** Fiche de l'organisation active : coordonnées, membres, suppression. */
    #[AdminRoute(path: '/organisation', name: 'organization_show', options: ['methods' => ['GET']])]
    public function show(): Response
    {
        if (null === $organization = $this->currentContext->getOrganization()) {
            return $this->noActiveOrganization();
        }

        $memberships = $organization->getMemberships()->toArray();
        usort($memberships, static fn (Membership $a, Membership $b) => strcasecmp((string) $a->getUser()?->getFullName(), (string) $b->getUser()?->getFullName()));

        $invitations = $organization->getInvitations()->toArray();
        usort($invitations, static fn (Invitation $a, Invitation $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $this->render('admin/organization/show.html.twig', [
            'organization' => $organization,
            'memberships' => $memberships,
            'invitations' => $invitations,
            'can_leave' => \count($memberships) > 1,
        ]);
    }

    #[AdminRoute(path: '/organisation/modifier', name: 'organization_edit', options: ['methods' => ['GET', 'POST']])]
    public function edit(Request $request): Response
    {
        if (null === $organization = $this->currentContext->getOrganization()) {
            return $this->noActiveOrganization();
        }

        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Les informations de l\'organisation sont enregistrées.');

            return $this->redirectToRoute('admin_organization_show');
        }

        // Formulaire invalide : ne pas afficher le nom en cours de saisie dans le menu et le sélecteur.
        if ($form->isSubmitted()) {
            $this->em->refresh($organization);
        }

        return $this->render('admin/organization/form.html.twig', [
            'form' => $form,
            'organization' => $organization,
        ]);
    }

    #[AdminRoute(path: '/organisation/invitations', name: 'organization_invite', options: ['methods' => ['POST']])]
    public function invite(Request $request, ValidatorInterface $validator): Response
    {
        if (null === $organization = $this->currentContext->getOrganization()) {
            return $this->noActiveOrganization();
        }
        if (!$this->isCsrfTokenValid('organization_invite', (string) $request->request->get('_token'))) {
            return $this->expired('admin_organization_show');
        }

        $email = trim((string) $request->request->get('email'));
        $violations = $validator->validate($email, [
            new Assert\NotBlank(message: 'Merci d\'indiquer l\'adresse email de la personne à inviter.'),
            new Assert\Email(message: 'Cette adresse email ne semble pas valide.'),
            new Assert\Length(max: 255),
        ]);
        if (\count($violations) > 0) {
            $this->addFlash('danger', (string) $violations[0]->getMessage());

            return $this->redirectToRoute('admin_organization_show');
        }

        try {
            $this->manager->invite($organization, $email, $this->getAppUser());
        } catch (InvitationException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('admin_organization_show');
        }

        $this->addFlash('success', sprintf('Invitation envoyée à %s. Le lien est valable 7 jours.', mb_strtolower($email)));

        return $this->redirectToRoute('admin_organization_show');
    }

    #[AdminRoute(path: '/organisation/invitations/{invitationId}/renvoyer', name: 'organization_invitation_resend', options: ['methods' => ['POST'], 'requirements' => ['invitationId' => '\d+']])]
    public function resendInvitation(Request $request, int $invitationId, InvitationRepository $invitations): Response
    {
        if (null === $invitation = $this->findActiveInvitation($request, $invitationId, $invitations, 'invitation_resend_')) {
            return $this->expired('admin_organization_show');
        }

        $this->manager->sendInvitation($invitation, $this->getAppUser());
        $this->addFlash('success', sprintf('Invitation renvoyée à %s, avec un nouveau lien valable 7 jours.', $invitation->getEmail()));

        return $this->redirectToRoute('admin_organization_show');
    }

    #[AdminRoute(path: '/organisation/invitations/{invitationId}/annuler', name: 'organization_invitation_cancel', options: ['methods' => ['POST'], 'requirements' => ['invitationId' => '\d+']])]
    public function cancelInvitation(Request $request, int $invitationId, InvitationRepository $invitations): Response
    {
        if (null === $invitation = $this->findActiveInvitation($request, $invitationId, $invitations, 'invitation_cancel_')) {
            return $this->expired('admin_organization_show');
        }

        $email = $invitation->getEmail();
        $this->manager->cancelInvitation($invitation);
        $this->addFlash('success', sprintf('L\'invitation de %s est annulée : son lien ne fonctionne plus.', $email));

        return $this->redirectToRoute('admin_organization_show');
    }

    /** Retire un membre de l'organisation active, ou permet à l'utilisateur de la quitter (son propre membership). */
    #[AdminRoute(path: '/organisation/membres/{membershipId}/retirer', name: 'organization_member_remove', options: ['methods' => ['POST'], 'requirements' => ['membershipId' => '\d+']])]
    public function removeMember(Request $request, int $membershipId, MembershipRepository $memberships): Response
    {
        if (null === $organization = $this->currentContext->getOrganization()) {
            return $this->noActiveOrganization();
        }
        if (!$this->isCsrfTokenValid('member_remove_'.$membershipId, (string) $request->request->get('_token'))) {
            return $this->expired('admin_organization_show');
        }

        $membership = $memberships->find($membershipId);
        if (null === $membership || $membership->getOrganization() !== $organization) {
            throw $this->createNotFoundException();
        }

        $user = $this->getAppUser();
        $isSelf = $membership->getUser() === $user;
        $name = (string) $membership->getUser()?->getFullName();

        try {
            $this->manager->removeMember($membership);
        } catch (LastMemberException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('admin_organization_show');
        }

        if ($isSelf) {
            $this->currentContext->switchTo(null);
            $this->addFlash('success', sprintf('Vous avez quitté « %s ». Vous êtes revenu·e à votre espace personnel.', $organization->getName()));

            return $this->redirectToRoute('admin');
        }

        $this->addFlash('success', sprintf('%s ne fait plus partie de l\'organisation. Ses listes et questions restent à l\'organisation.', $name));

        return $this->redirectToRoute('admin_organization_show');
    }

    /** Supprime l'organisation active et tout son contenu, après saisie de son nom. */
    #[AdminRoute(path: '/organisation/supprimer', name: 'organization_delete', options: ['methods' => ['POST']])]
    public function delete(Request $request): Response
    {
        if (null === $organization = $this->currentContext->getOrganization()) {
            return $this->noActiveOrganization();
        }
        if (!$this->isCsrfTokenValid('organization_delete', (string) $request->request->get('_token'))) {
            return $this->expired('admin_organization_show');
        }

        if (trim((string) $request->request->get('confirm_name')) !== $organization->getName()) {
            $this->addFlash('danger', 'Le nom saisi ne correspond pas : l\'organisation n\'a pas été supprimée.');

            return $this->redirectToRoute('admin_organization_show');
        }

        $name = $organization->getName();
        $this->manager->delete($organization);
        $this->currentContext->switchTo(null);
        $this->addFlash('success', sprintf('L\'organisation « %s » et tout son contenu ont été supprimés.', $name));

        return $this->redirectToRoute('admin');
    }

    /**
     * Invitation de l'organisation active visée par une action, après contrôle du jeton CSRF.
     * Null si le jeton est invalide ; 404 si l'invitation n'existe pas ou appartient à une autre organisation.
     */
    private function findActiveInvitation(Request $request, int $invitationId, InvitationRepository $invitations, string $csrfPrefix): ?Invitation
    {
        if (!$this->isCsrfTokenValid($csrfPrefix.$invitationId, (string) $request->request->get('_token'))) {
            return null;
        }

        $invitation = $invitations->find($invitationId);
        $organization = $this->currentContext->getOrganization();
        if (null === $invitation || null === $organization || $invitation->getOrganization() !== $organization) {
            throw $this->createNotFoundException();
        }

        return $invitation;
    }

    private function noActiveOrganization(): Response
    {
        $this->addFlash('warning', 'Choisissez d\'abord une organisation dans le sélecteur « Espace de travail ».');

        return $this->redirectToRoute('admin');
    }

    private function expired(string $route = 'admin'): Response
    {
        $this->addFlash('danger', 'Votre session a expiré, merci de réessayer.');

        return $this->redirectToRoute($route);
    }

    private function getAppUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
