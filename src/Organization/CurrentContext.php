<?php

namespace App\Organization;

use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Contexte de travail choisi dans le sélecteur global : une organisation, ou le mode personnel (null).
 *
 * Stocké en session ; à défaut (nouvelle session), on reprend le dernier choix mémorisé sur le compte.
 * L'appartenance est revérifiée à chaque requête : un membre retiré repasse en mode personnel.
 *
 * Réinitialisé entre deux requêtes (FrankenPHP tourne en mode worker).
 */
final class CurrentContext implements ResetInterface
{
    private const SESSION_KEY = 'app.current_organization';

    private bool $resolved = false;
    private ?Organization $organization = null;

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** Organisation active, ou null en mode personnel. */
    public function getOrganization(): ?Organization
    {
        if (!$this->resolved) {
            $this->organization = $this->resolve();
            $this->resolved = true;
        }

        return $this->organization;
    }

    public function isPersonal(): bool
    {
        return null === $this->getOrganization();
    }

    /**
     * Change de contexte (null = personnel) et le mémorise sur le compte pour la prochaine connexion.
     *
     * @throws \InvalidArgumentException si l'utilisateur n'est pas membre de l'organisation
     */
    public function switchTo(?Organization $organization): void
    {
        $user = $this->getUser();
        if (null === $user) {
            throw new \LogicException('Aucun utilisateur connecté.');
        }
        if (null !== $organization && !$organization->hasMember($user)) {
            throw new \InvalidArgumentException('Vous n\'êtes pas membre de cette organisation.');
        }

        $this->requestStack->getSession()->set(self::SESSION_KEY, $organization?->getId());
        $user->setLastOrganization($organization);
        $this->em->flush();

        $this->organization = $organization;
        $this->resolved = true;
    }

    /** Organisations proposées dans le sélecteur. */
    public function getAvailableOrganizations(): array
    {
        $organizations = $this->getUser()?->getOrganizations() ?? [];
        usort($organizations, static fn (Organization $a, Organization $b) => strcasecmp((string) $a->getName(), (string) $b->getName()));

        return $organizations;
    }

    public function reset(): void
    {
        $this->resolved = false;
        $this->organization = null;
    }

    private function resolve(): ?Organization
    {
        $user = $this->getUser();
        if (null === $user) {
            return null;
        }

        $session = $this->requestStack->getCurrentRequest()?->hasSession()
            ? $this->requestStack->getSession()
            : null;

        // Clé présente en session (même à null = personnel) : c'est le choix de cette session.
        if (null !== $session && $session->has(self::SESSION_KEY)) {
            $id = $session->get(self::SESSION_KEY);
        } else {
            $id = $user->getLastOrganization()?->getId();
        }

        if (null === $id) {
            return null;
        }

        foreach ($user->getOrganizations() as $organization) {
            if ($organization->getId() === $id) {
                return $organization;
            }
        }

        // Plus membre (retiré, organisation supprimée) : retour au mode personnel.
        $session?->set(self::SESSION_KEY, null);

        return null;
    }

    private function getUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
