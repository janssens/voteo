<?php

namespace App\Entity;

use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Groupe d'organisateurs qui partagent listes et questions.
 * Tous les membres ont les mêmes droits (CRUD, inviter, retirer un membre).
 */
#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
class Organization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    /** Adresse de réponse (reply-to) des emails envoyés au nom de l'organisation. */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    private ?string $contactEmail = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Membership>
     */
    #[ORM\OneToMany(targetEntity: Membership::class, mappedBy: 'organization', orphanRemoval: true, cascade: ['persist'])]
    private Collection $memberships;

    /**
     * @var Collection<int, Invitation>
     */
    #[ORM\OneToMany(targetEntity: Invitation::class, mappedBy: 'organization', orphanRemoval: true)]
    private Collection $invitations;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
        $this->invitations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(string $contactEmail): static
    {
        $this->contactEmail = $contactEmail;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Membership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMember(User $user, ?User $invitedBy = null): Membership
    {
        foreach ($this->memberships as $membership) {
            if ($membership->getUser() === $user) {
                return $membership;
            }
        }

        $membership = new Membership($user, $this, $invitedBy);
        $this->memberships->add($membership);

        return $membership;
    }

    public function removeMembership(Membership $membership): static
    {
        $this->memberships->removeElement($membership);

        return $this;
    }

    public function hasMember(User $user): bool
    {
        foreach ($this->memberships as $membership) {
            if ($membership->getUser()?->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, Invitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
