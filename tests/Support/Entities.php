<?php

namespace App\Tests\Support;

use App\Entity\ContactList;
use App\Entity\Organization;
use App\Entity\Question;
use App\Entity\User;

/**
 * Fabrique d'entités pour les tests unitaires (sans base de données : les ids sont posés par réflexion).
 */
trait Entities
{
    private int $nextId = 1;

    protected function withId(object $entity, ?int $id = null): object
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id ?? $this->nextId++);

        return $entity;
    }

    protected function user(string $firstname = 'Alice', string $email = 'alice@example.test', array $roles = []): User
    {
        $user = (new User())
            ->setFirstname($firstname)
            ->setLastname('Test')
            ->setEmail($email)
            ->setUsername(strtolower($firstname))
            ->setPassword('x')
            ->setRoles($roles);

        return $this->withId($user);
    }

    protected function organization(string $name = 'Asso Jardins partagés', User ...$members): Organization
    {
        $organization = $this->withId((new Organization())->setName($name)->setContactEmail('bureau@jardins.test'));
        foreach ($members as $member) {
            $this->withId($organization->addMember($member));
        }

        return $organization;
    }

    protected function question(?User $owner, ?Organization $organization = null): Question
    {
        return $this->withId((new Question())->setOwner($owner)->setOrganization($organization)->setSubject('Pique-nique ?'));
    }

    protected function contactList(?User $owner, ?Organization $organization = null): ContactList
    {
        return $this->withId((new ContactList())->setName('Adhérents')->setOwner($owner)->setOrganization($organization));
    }
}
