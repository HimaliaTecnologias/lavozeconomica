<?php

namespace App\Entity\Site;

use App\Repository\Site\ArticleRatingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRatingRepository::class)]
#[ORM\Table(name: 'article_ratings')]
#[ORM\UniqueConstraint(name: 'uniq_article_user_rating', columns: ['article_id', 'user_id'])]
class ArticleRating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'article_id', nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    /** ID del usuario en la BD plataforma. */
    #[ORM\Column(name: 'user_id')]
    private int $userId;

    #[ORM\Column(type: 'smallint')]
    private int $score;

    #[ORM\Column(name: 'rated_at')]
    private \DateTimeImmutable $ratedAt;

    public function __construct(Article $article, int $userId, int $score)
    {
        $this->article = $article;
        $this->userId = $userId;
        $this->score = $score;
        $this->ratedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;
        $this->ratedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getRatedAt(): \DateTimeImmutable
    {
        return $this->ratedAt;
    }
}
