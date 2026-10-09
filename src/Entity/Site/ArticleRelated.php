<?php

namespace App\Entity\Site;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'article_related')]
#[ORM\UniqueConstraint(name: 'uniq_article_related_pair', columns: ['article_id', 'related_article_id'])]
#[ORM\Index(columns: ['article_id', 'sort_order'], name: 'idx_article_related_sort')]
class ArticleRelated
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'relatedLinks')]
    #[ORM\JoinColumn(name: 'article_id', nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'related_article_id', nullable: false, onDelete: 'CASCADE')]
    private Article $relatedArticle;

    #[ORM\Column(name: 'sort_order', options: ['default' => 0])]
    private int $sortOrder = 0;

    public function __construct(Article $article, Article $relatedArticle, int $sortOrder = 0)
    {
        $this->article = $article;
        $this->relatedArticle = $relatedArticle;
        $this->sortOrder = $sortOrder;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getRelatedArticle(): Article
    {
        return $this->relatedArticle;
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
}
