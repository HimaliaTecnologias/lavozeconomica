<?php

namespace App\Entity\Platform;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_site_roles')]
class UserSiteRole
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 64)]
    private string $role;

    public function __construct(User $user, Site $site, string $role)
    {
        $this->user = $user;
        $this->site = $site;
        $this->role = $role;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSite(): Site
    {
        return $this->site;
    }
}
