<?php

namespace App\Repository;

use App\Entity\Invitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    /**
     * Supprime les invitations dont le lien a expiré avant $before (avec l'adresse de la personne invitée).
     *
     * @return int nombre d'invitations supprimées
     */
    public function deleteExpiredBefore(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('i')
            ->delete()
            ->where('i.expiresAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
