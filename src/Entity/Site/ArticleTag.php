<?php

namespace App\Entity\Site;

use App\Repository\Site\ArticleTagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleTagRepository::class)]
#[ORM\Table(name: 'article_tags')]
#[ORM\UniqueConstraint(name: 'uniq_article_tag_slug', columns: ['slug'])]
class ArticleTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 120)]
    private string $slug;

    /** @var Collection<int, Article> */
    #[ORM\ManyToMany(targetEntity: Article::class, mappedBy: 'tags')]
    private Collection $articles;

    public function __construct(string $name, string $slug)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->articles = new ArrayCollection();
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

    /** @return Collection<int, Article> */
    public function getArticles(): Collection
    {
        return $this->articles;
    }
}
