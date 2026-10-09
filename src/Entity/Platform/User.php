<?php

namespace App\Entity\Platform;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPlatformSuperadmin = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'email_verified_at', nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(name: 'email_verification_token', length: 64, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(name: 'email_verification_expires_at', nullable: true)]
    private ?\DateTimeImmutable $emailVerificationExpiresAt = null;

    #[ORM\Column(name: 'last_login_at', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(name: 'last_login_ip', length: 45, nullable: true)]
    private ?string $lastLoginIp = null;

    public function __construct(string $email, string $name, string $passwordHash, bool $isPlatformSuperadmin = false)
    {
        $this->email = strtolower($email);
        $this->name = $name;
        $this->passwordHash = $passwordHash;
        $this->isPlatformSuperadmin = $isPlatformSuperadmin;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = strtolower($email);

        return $this;
    }

    public function isPlatformSuperadmin(): bool
    {
        return $this->isPlatformSuperadmin;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        if ($this->isPlatformSuperadmin) {
            $roles[] = 'ROLE_PLATFORM_SUPERADMIN';
        }

        return $roles;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    public function eraseCredentials(): void
    {
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function markEmailVerified(): self
    {
        $this->emailVerifiedAt = new \DateTimeImmutable();
        $this->emailVerificationToken = null;
        $this->emailVerificationExpiresAt = null;

        return $this;
    }

    public function issueEmailVerificationToken(): self
    {
        $this->emailVerificationToken = bin2hex(random_bytes(32));
        $this->emailVerificationExpiresAt = new \DateTimeImmutable('+48 hours');

        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function isEmailVerificationTokenValid(string $token): bool
    {
        if ($this->emailVerificationToken === null || $this->emailVerificationToken !== $token) {
            return false;
        }

        if ($this->emailVerificationExpiresAt !== null && $this->emailVerificationExpiresAt < new \DateTimeImmutable()) {
            return false;
        }

        return true;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->lastLoginIp;
    }

    public function recordLogin(?string $ip = null): self
    {
        $this->lastLoginAt = new \DateTimeImmutable();
        $ip = $ip !== null ? trim($ip) : '';
        $this->lastLoginIp = $ip !== '' ? mb_substr($ip, 0, 45) : null;

        return $this;
    }
}
