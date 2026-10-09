<?php

namespace App\Entity\Site;

use App\Repository\Site\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'categories')]
#[ORM\UniqueConstraint(name: 'uniq_category_slug', columns: ['slug'])]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $parent = null;

    /** @var Collection<int, Category> */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'name' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $showInNav = true;

    public function __construct(string $name, string $slug)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->children = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getParent(): ?Category
    {
        return $this->parent;
    }

    public function setParent(?Category $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /** @return Collection<int, Category> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    public function getDepth(): int
    {
        $depth = 0;
        $current = $this->parent;
        while ($current !== null) {
            ++$depth;
            $current = $current->getParent();
        }

        return $depth;
    }

    public function isDescendantOf(Category $other): bool
    {
        $current = $this->parent;
        while ($current !== null) {
            if ($current->getId() === $other->getId()) {
                return true;
            }
            $current = $current->getParent();
        }

        return false;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isShowInNav(): bool
    {
        return $this->showInNav;
    }

    public function setShowInNav(bool $showInNav): self
    {
        $this->showInNav = $showInNav;

        return $this;
    }

    public function getBreadcrumbLabel(): string
    {
        if ($this->parent === null) {
            return $this->name;
        }

        return $this->parent->getName().' › '.$this->name;
    }
}
