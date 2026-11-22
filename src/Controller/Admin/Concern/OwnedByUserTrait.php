<?php

namespace App\Controller\Admin\Concern;

use App\Entity\Organization;
use App\Organization\CurrentContext;
use App\Security\Voter\OwnerVoter;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Cloisonne un CRUD EasyAdmin selon le contexte actif (sélecteur global) :
 *  - la liste n'affiche que les entités du contexte : celles de l'organisation active,
 *    ou en mode personnel celles de l'utilisateur hors organisation (sauf ROLE_ADMIN, qui voit tout) ;
 *  - détail, édition et suppression (y compris groupée) passent par OwnerVoter.
 *
 * La classe qui l'utilise définit scopeRoot() : l'alias DQL de la ressource qui porte
 * "organization" et "owner" (Question ou ContactList), et les jointures pour y arriver.
 * Si elle redéfinit configureCrud(), elle doit appeler restrictToOwner().
 */
trait OwnedByUserTrait
{
    private CurrentContext $currentContext;

    /**
     * @return array{0: string, 1: array<string, string>} [alias de la ressource racine, [alias => jointure]]
     */
    abstract protected static function scopeRoot(): array;

    #[Required]
    public function setCurrentContext(CurrentContext $currentContext): void
    {
        $this->currentContext = $currentContext;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->restrictToOwner($crud);
    }

    protected function restrictToOwner(Crud $crud): Crud
    {
        return $crud->setEntityPermission(OwnerVoter::OWNER);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        if ($this->isGranted('ROLE_ADMIN')) {
            return $qb;
        }

        [$root, $joins] = static::scopeRoot();
        foreach ($joins as $alias => $join) {
            $qb->innerJoin($join, $alias);
        }

        return self::applyScope($qb, $root, $this->currentContext->getOrganization(), $this->getUser());
    }

    /**
     * Restreint $qb aux ressources d'une organisation, ou (si null) aux ressources personnelles de $owner.
     * Sert à l'index et aux listes déroulantes (ex. listes proposées pour une question).
     */
    protected static function applyScope(QueryBuilder $qb, string $root, ?Organization $organization, ?UserInterface $owner): QueryBuilder
    {
        if (null !== $organization) {
            return $qb->andWhere($root.'.organization = :scope_organization')
                ->setParameter('scope_organization', $organization);
        }

        return $qb->andWhere($root.'.organization IS NULL')
            ->andWhere($root.'.owner = :scope_owner')
            ->setParameter('scope_owner', $owner);
    }
}
