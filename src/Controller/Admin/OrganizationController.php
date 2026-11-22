<?php

namespace App\Controller\Admin;

use App\Entity\Organization;
use App\Entity\User;
use App\Form\OrganizationType;
use App\Organization\CurrentContext;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sélecteur global de contexte (personnel / organisation) et gestion des organisations.
 * Routes admin (layout EasyAdmin) : admin_context_switch, admin_organization_new.
 */
#[IsGranted('ROLE_USER')]
final class OrganizationController extends AbstractController
{
    public function __construct(
        private readonly CurrentContext $currentContext,
        private readonly EntityManagerInterface $em,
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
            $this->addFlash('danger', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('admin');
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
        /** @var User $user */
        $user = $this->getUser();

        $organization = (new Organization())->setContactEmail((string) $user->getEmail());
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $organization->addMember($user);
            $this->em->persist($organization);
            $this->em->flush();

            $this->currentContext->switchTo($organization);
            $this->addFlash('success', sprintf('L\'organisation « %s » est créée. Les listes et questions que vous créez maintenant lui appartiennent.', $organization->getName()));

            return $this->redirectToRoute('admin');
        }

        return $this->render('admin/organization/new.html.twig', [
            'form' => $form,
        ]);
    }
}
