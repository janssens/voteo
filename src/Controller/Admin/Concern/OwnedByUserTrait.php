<?php

namespace App\Controller\Admin\Concern;

use App\Security\Voter\OwnerVoter;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;

/**
 * Cloisonne un CRUD EasyAdmin par organisateur :
 *  - la liste n'affiche que ses propres entités (sauf ROLE_ADMIN) ;
 *  - détail, édition et suppression (y compris groupée) passent par OwnerVoter.
 *
 * La classe qui l'utilise définit ownerPath() : le chemin DQL vers le propriétaire
 * depuis l'alias racine "entity", et les jointures nécessaires.
 */
trait OwnedByUserTrait
{
    /**
     * @return array{0: string, 1: array<string, string>} [chemin du propriétaire, [alias => jointure]]
     */
    abstract protected static function ownerPath(): array;

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityPermission(OwnerVoter::OWNER);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        if ($this->isGranted('ROLE_ADMIN')) {
            return $qb;
        }

        [$ownerPath, $joins] = static::ownerPath();
        foreach ($joins as $alias => $join) {
            $qb->innerJoin($join, $alias);
        }

        return $qb->andWhere($ownerPath.' = :current_owner')
            ->setParameter('current_owner', $this->getUser());
    }
}
