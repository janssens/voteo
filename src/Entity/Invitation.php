<?php

namespace App\Entity;

use App\Repository\InvitationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Invitation à rejoindre une organisation, acceptée via un lien secret.
 * Seul le hash du jeton est stocké : le jeton en clair n'existe que dans l'email envoyé.
 */
#[ORM\Entity(repositoryClass: InvitationRepository::class)]
class Invitation
{
    public const TTL = '+7 days';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'invitations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Organization $organization = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    /** SHA-256 (hex) du jeton envoyé par email. */
    #[ORM\Column(length: 64, unique: true)]
    private ?string $tokenHash = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $invitedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    public function __construct(Organization $organization, string $email, ?User $invitedBy)
    {
        $this->organization = $organization;
        $this->email = $email;
        $this->invitedBy = $invitedBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify(self::TTL);
    }

    /**
     * Génère un nouveau jeton (invalide le précédent) et repousse l'expiration.
     *
     * @return string le jeton en clair, à mettre dans le lien de l'email
     */
    public function renewToken(): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->tokenHash = self::hashToken($token);
        $this->expiresAt = (new \DateTimeImmutable())->modify(self::TTL);

        return $token;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt <= ($now ?? new \DateTimeImmutable());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function getInvitedBy(): ?User
    {
        return $this->invitedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
