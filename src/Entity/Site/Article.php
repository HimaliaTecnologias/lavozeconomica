<?php

namespace App\Entity\Site;

use App\Entity\Site\Enum\ArticleStatus;
use App\Repository\Site\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'articles')]
#[ORM\UniqueConstraint(name: 'uniq_slug', columns: ['slug'])]
#[ORM\Index(columns: ['status', 'published_at'], name: 'idx_status_published')]
#[ORM\Index(columns: ['status', 'view_count'], name: 'idx_status_views')]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 512)]
    private string $title;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lead = null;

    #[ORM\Column(type: 'text')]
    private string $bodyHtml;

    #[ORM\Column(length: 512)]
    private string $slug;

    #[ORM\Column(length: 32, enumType: ArticleStatus::class)]
    private ArticleStatus $status;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: Poll::class)]
    #[ORM\JoinColumn(name: 'poll_id', nullable: true, onDelete: 'SET NULL')]
    private ?Poll $poll = null;

    #[ORM\ManyToOne(targetEntity: Gallery::class)]
    #[ORM\JoinColumn(name: 'gallery_id', nullable: true, onDelete: 'SET NULL')]
    private ?Gallery $gallery = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(name: 'video_id', nullable: true, onDelete: 'SET NULL')]
    private ?Media $video = null;

    #[ORM\Column(name: 'video_replaces_featured', options: ['default' => false])]
    private bool $videoReplacesFeatured = false;

    /** Referencia lógica a ingested_stories en BD plataforma. */
    #[ORM\Column(nullable: true)]
    private ?int $ingestedStoryId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ingestedExternalId = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    /** Inicio de vigencia para la sección Agenda (conciertos, actividades…). */
    #[ORM\Column(name: 'agenda_starts_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $agendaStartsAt = null;

    /** Fin de vigencia para la sección Agenda. */
    #[ORM\Column(name: 'agenda_ends_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $agendaEndsAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'view_count', options: ['default' => 0])]
    private int $viewCount = 0;

    #[ORM\Column(name: 'show_on_home', options: ['default' => true])]
    private bool $showOnHome = true;

    /** Chincheta: prioriza la noticia en las 4 destacadas de portada. */
    #[ORM\Column(name: 'is_pinned', options: ['default' => false])]
    private bool $isPinned = false;

    /** Nombre público del autor (firma). */
    #[ORM\Column(name: 'author_name', length: 255, nullable: true)]
    private ?string $authorName = null;

    /** Referencia lógica a users en BD plataforma. */
    #[ORM\Column(name: 'author_user_id', nullable: true)]
    private ?int $authorUserId = null;

    /** @var Collection<int, ArticleImage> */
    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $images;

    /** @var Collection<int, ArticleTag> */
    #[ORM\ManyToMany(targetEntity: ArticleTag::class, inversedBy: 'articles')]
    #[ORM\JoinTable(name: 'article_article_tag')]
    private Collection $tags;

    /** @var Collection<int, ArticleRelated> */
    #[ORM\OneToMany(mappedBy: 'article', targetEntity: ArticleRelated::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $relatedLinks;

    public function __construct(string $title, string $bodyHtml, string $slug, ArticleStatus $status = ArticleStatus::Draft)
    {
        $this->title = $title;
        $this->bodyHtml = $bodyHtml;
        $this->slug = $slug;
        $this->status = $status;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->images = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->relatedLinks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        $this->touch();

        return $this;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;
        $this->touch();

        return $this;
    }

    public function getLead(): ?string
    {
        return $this->lead;
    }

    public function setLead(?string $lead): self
    {
        $this->lead = $lead;
        $this->touch();

        return $this;
    }

    public function getBodyHtml(): string
    {
        return $this->bodyHtml;
    }

    public function setBodyHtml(string $bodyHtml): self
    {
        $this->bodyHtml = $bodyHtml;
        $this->touch();

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        $this->touch();

        return $this;
    }

    public function getStatus(): ArticleStatus
    {
        return $this->status;
    }

    public function setStatus(ArticleStatus $status): self
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function publish(): self
    {
        $this->status = ArticleStatus::Published;
        $this->publishedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function linkIngestedStory(int $ingestedStoryId, string $externalId): self
    {
        $this->ingestedStoryId = $ingestedStoryId;
        $this->ingestedExternalId = $externalId;

        return $this;
    }

    public function getIngestedStoryId(): ?int
    {
        return $this->ingestedStoryId;
    }

    public function getIngestedExternalId(): ?string
    {
        return $this->ingestedExternalId;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        $this->touch();

        return $this;
    }

    public function getPoll(): ?Poll
    {
        return $this->poll;
    }

    public function setPoll(?Poll $poll): self
    {
        $this->poll = $poll;
        $this->touch();

        return $this;
    }

    public function getGallery(): ?Gallery
    {
        return $this->gallery;
    }

    public function setGallery(?Gallery $gallery): self
    {
        $this->gallery = $gallery;
        $this->touch();

        return $this;
    }

    public function getVideo(): ?Media
    {
        return $this->video;
    }

    public function setVideo(?Media $video): self
    {
        $this->video = $video;
        $this->touch();

        return $this;
    }

    public function isVideoReplacesFeatured(): bool
    {
        return $this->videoReplacesFeatured;
    }

    public function setVideoReplacesFeatured(bool $videoReplacesFeatured): self
    {
        $this->videoReplacesFeatured = $videoReplacesFeatured;
        $this->touch();

        return $this;
    }

    public function isFromIngest(): bool
    {
        return $this->ingestedStoryId !== null;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function incrementViewCount(): self
    {
        ++$this->viewCount;

        return $this;
    }

    public function isShowOnHome(): bool
    {
        return $this->showOnHome;
    }

    public function setShowOnHome(bool $showOnHome): self
    {
        $this->showOnHome = $showOnHome;
        $this->touch();

        return $this;
    }

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    public function setPinned(bool $isPinned): self
    {
        $this->isPinned = $isPinned;
        if ($isPinned) {
            $this->showOnHome = true;
        }
        $this->touch();

        return $this;
    }

    public function getAuthorName(): ?string
    {
        return $this->authorName;
    }

    public function setAuthorName(?string $authorName): self
    {
        $authorName = $authorName !== null ? trim($authorName) : null;
        $this->authorName = $authorName !== '' ? $authorName : null;
        $this->touch();

        return $this;
    }

    public function getAuthorUserId(): ?int
    {
        return $this->authorUserId;
    }

    public function setAuthorUserId(?int $authorUserId): self
    {
        $this->authorUserId = $authorUserId;
        $this->touch();

        return $this;
    }

    /** @return Collection<int, ArticleImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function getFeaturedImage(): ?ArticleImage
    {
        foreach ($this->images as $image) {
            if ($image->isFeatured()) {
                return $image;
            }
        }

        $first = $this->images->first();

        return $first === false ? null : $first;
    }

    /**
     * Imágenes de galería antiguas (article_images no destacadas), p. ej. antes de Gallery.
     *
     * @return list<ArticleImage>
     */
    public function getLegacyGalleryImages(): array
    {
        $featured = $this->getFeaturedImage();
        $out = [];
        foreach ($this->images as $image) {
            if ($featured !== null && $image === $featured) {
                continue;
            }
            if ($image->isFeatured()) {
                continue;
            }
            $out[] = $image;
        }

        return $out;
    }

    public function addImage(ArticleImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
        }

        return $this;
    }

    public function clearImages(): self
    {
        $this->images->clear();

        return $this;
    }

    /** @return Collection<int, ArticleTag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(ArticleTag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(ArticleTag $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    /** @return Collection<int, ArticleRelated> */
    public function getRelatedLinks(): Collection
    {
        return $this->relatedLinks;
    }

    /**
     * @return list<Article>
     */
    public function getRelatedArticles(): array
    {
        $out = [];
        foreach ($this->relatedLinks as $link) {
            $out[] = $link->getRelatedArticle();
        }

        return $out;
    }

    public function addRelatedLink(ArticleRelated $link): self
    {
        if (!$this->relatedLinks->contains($link)) {
            $this->relatedLinks->add($link);
        }

        return $this;
    }

    public function clearRelatedLinks(): self
    {
        $this->relatedLinks->clear();

        return $this;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;
        $this->touch();

        return $this;
    }

    public function getAgendaStartsAt(): ?\DateTimeImmutable
    {
        return $this->agendaStartsAt;
    }

    public function setAgendaStartsAt(?\DateTimeImmutable $agendaStartsAt): self
    {
        $this->agendaStartsAt = $agendaStartsAt;
        $this->touch();

        return $this;
    }

    public function getAgendaEndsAt(): ?\DateTimeImmutable
    {
        return $this->agendaEndsAt;
    }

    public function setAgendaEndsAt(?\DateTimeImmutable $agendaEndsAt): self
    {
        $this->agendaEndsAt = $agendaEndsAt;
        $this->touch();

        return $this;
    }

    public function hasAgendaWindow(): bool
    {
        return $this->agendaStartsAt !== null && $this->agendaEndsAt !== null;
    }

    /**
     * True si el intervalo de agenda solapa el día calendario indicado (Europe/Madrid).
     */
    public function isOnAgendaForDate(\DateTimeImmutable $date): bool
    {
        if (!$this->hasAgendaWindow()) {
            return false;
        }

        $tz = new \DateTimeZone('Europe/Madrid');
        $local = $date->setTimezone($tz);
        $dayStart = new \DateTimeImmutable($local->format('Y-m-d').' 00:00:00', $tz);
        $dayEnd = $dayStart->modify('+1 day');

        $starts = $this->asMadridLocal($this->agendaStartsAt);
        $ends = $this->asMadridLocal($this->agendaEndsAt);

        return $starts < $dayEnd && $ends >= $dayStart;
    }

    public function schedule(\DateTimeImmutable $scheduledAt): self
    {
        $this->status = ArticleStatus::Scheduled;
        $this->scheduledAt = $scheduledAt;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    private function asMadridLocal(\DateTimeImmutable $value): \DateTimeImmutable
    {
        $tz = new \DateTimeZone('Europe/Madrid');
        if ($value->getTimezone()->getName() === $tz->getName()) {
            return $value;
        }

        return new \DateTimeImmutable($value->format('Y-m-d H:i:s'), $tz);
    }
}
