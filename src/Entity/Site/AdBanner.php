<?php

namespace App\Entity\Site;

use App\Entity\Site\Enum\AdCreativeType;
use App\Entity\Site\Enum\AdFormat;
use App\Entity\Site\Enum\AdPlacement;
use App\Repository\Site\AdBannerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdBannerRepository::class)]
#[ORM\Table(name: 'ad_banners')]
#[ORM\Index(columns: ['placement', 'is_active', 'sort_order'], name: 'idx_ad_placement_active')]
class AdBanner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 64, enumType: AdPlacement::class)]
    private AdPlacement $placement;

    /** @var list<string>|null */
    #[ORM\Column(name: 'placements_json', type: 'json', nullable: true)]
    private ?array $placementsJson = [];

    #[ORM\Column(length: 64, enumType: AdFormat::class)]
    private AdFormat $format;

    #[ORM\Column(name: 'creative_type', length: 32, enumType: AdCreativeType::class, options: ['default' => 'image'])]
    private AdCreativeType $creativeType = AdCreativeType::Image;

    #[ORM\Column(name: 'image_url', length: 2048, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(name: 'link_url', length: 2048, nullable: true)]
    private ?string $linkUrl = null;

    #[ORM\Column(name: 'html_content', type: 'text', nullable: true)]
    private ?string $htmlContent = null;

    #[ORM\Column(name: 'alt_text', length: 512, nullable: true)]
    private ?string $altText = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $headline = null;

    #[ORM\Column(name: 'body_text', type: 'text', nullable: true)]
    private ?string $bodyText = null;

    #[ORM\Column(name: 'cta_label', length: 80, nullable: true)]
    private ?string $ctaLabel = null;

    #[ORM\Column(name: 'bg_color', length: 7, nullable: true)]
    private ?string $bgColor = null;

    #[ORM\Column(name: 'text_color', length: 7, nullable: true)]
    private ?string $textColor = null;

    #[ORM\Column(name: 'accent_color', length: 7, nullable: true)]
    private ?string $accentColor = null;

    #[ORM\Column(name: 'preset_key', length: 64, nullable: true)]
    private ?string $presetKey = null;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'sort_order', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'starts_at', nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(name: 'ends_at', nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'click_count', options: ['default' => 0])]
    private int $clickCount = 0;

    /** @var list<int>|null IDs de categorías; vacío = todas */
    #[ORM\Column(name: 'category_ids_json', type: 'json', nullable: true)]
    private ?array $categoryIdsJson = [];

    /** @var list<int>|null IDs de tags; vacío = todas */
    #[ORM\Column(name: 'tag_ids_json', type: 'json', nullable: true)]
    private ?array $tagIdsJson = [];

    public function __construct(string $name, AdPlacement $placement, AdFormat $format)
    {
        $this->name = $name;
        $this->format = $format;
        $this->createdAt = new \DateTimeImmutable();
        $this->setPlacements([$placement]);
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

    /**
     * Ubicación principal (primera de la lista). Conservada por compatibilidad.
     */
    public function getPlacement(): AdPlacement
    {
        $placements = $this->getPlacements();

        return $placements[0] ?? $this->placement;
    }

    public function setPlacement(AdPlacement $placement): self
    {
        return $this->setPlacements([$placement]);
    }

    /**
     * @return list<AdPlacement>
     */
    public function getPlacements(): array
    {
        $values = $this->placementsJson ?? [];
        if ($values === []) {
            return [$this->placement];
        }

        $placements = [];
        foreach ($values as $value) {
            if (!\is_string($value)) {
                continue;
            }
            $placement = AdPlacement::tryFrom($value);
            if ($placement !== null) {
                $placements[] = $placement;
            }
        }

        return $placements !== [] ? $placements : [$this->placement];
    }

    /**
     * @return list<string>
     */
    public function getPlacementValues(): array
    {
        return array_map(
            static fn (AdPlacement $placement): string => $placement->value,
            $this->getPlacements(),
        );
    }

    public function hasPlacement(AdPlacement|string $placement): bool
    {
        $value = $placement instanceof AdPlacement ? $placement->value : $placement;

        return \in_array($value, $this->getPlacementValues(), true);
    }

    /**
     * @param list<AdPlacement> $placements
     */
    public function setPlacements(array $placements): self
    {
        $unique = [];
        foreach ($placements as $placement) {
            if (!$placement instanceof AdPlacement) {
                continue;
            }
            $unique[$placement->value] = $placement;
        }
        if ($unique === []) {
            throw new \InvalidArgumentException('El banner necesita al menos una ubicación.');
        }

        $list = array_values($unique);
        $this->placement = $list[0];
        $this->placementsJson = array_map(
            static fn (AdPlacement $placement): string => $placement->value,
            $list,
        );

        return $this;
    }

    /**
     * @return list<int>
     */
    public function getCategoryIds(): array
    {
        return $this->normalizeIdList($this->categoryIdsJson);
    }

    /**
     * @param list<int> $ids
     */
    public function setCategoryIds(array $ids): self
    {
        $this->categoryIdsJson = $this->normalizeIdList($ids);

        return $this;
    }

    /**
     * @return list<int>
     */
    public function getTagIds(): array
    {
        return $this->normalizeIdList($this->tagIdsJson);
    }

    /**
     * @param list<int> $ids
     */
    public function setTagIds(array $ids): self
    {
        $this->tagIdsJson = $this->normalizeIdList($ids);

        return $this;
    }

    public function hasCategoryTargeting(): bool
    {
        return $this->getCategoryIds() !== [];
    }

    public function hasTagTargeting(): bool
    {
        return $this->getTagIds() !== [];
    }

    public function isGloballyTargeted(): bool
    {
        return !$this->hasCategoryTargeting() && !$this->hasTagTargeting();
    }

    /**
     * Sin restricciones → visible en cualquier contexto.
     * Con categorías y/o tags → visible si coincide la categoría o alguno de los tags.
     *
     * @param list<int> $tagIds
     */
    public function matchesTargeting(?int $categoryId, array $tagIds = []): bool
    {
        if ($this->isGloballyTargeted()) {
            return true;
        }

        $categoryIds = $this->getCategoryIds();
        $targetTagIds = $this->getTagIds();
        $pageTagIds = $this->normalizeIdList($tagIds);

        if ($categoryIds !== [] && $categoryId !== null && \in_array($categoryId, $categoryIds, true)) {
            return true;
        }

        if ($targetTagIds !== [] && array_intersect($targetTagIds, $pageTagIds) !== []) {
            return true;
        }

        return false;
    }

    public function getFormat(): AdFormat
    {
        return $this->format;
    }

    public function setFormat(AdFormat $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function getCreativeType(): AdCreativeType
    {
        return $this->creativeType;
    }

    public function setCreativeType(AdCreativeType $creativeType): self
    {
        $this->creativeType = $creativeType;

        return $this;
    }

    /**
     * Tipo efectivo para renderizar (compatibilidad con banners antiguos).
     */
    public function resolveCreativeType(): AdCreativeType
    {
        if ($this->creativeType === AdCreativeType::Image
            && ($this->imageUrl === null || $this->imageUrl === '')
            && $this->htmlContent !== null && $this->htmlContent !== ''
            && ($this->headline === null || $this->headline === '')
        ) {
            return AdCreativeType::Html;
        }

        return $this->creativeType;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getLinkUrl(): ?string
    {
        return $this->linkUrl;
    }

    public function setLinkUrl(?string $linkUrl): self
    {
        $this->linkUrl = $linkUrl;

        return $this;
    }

    public function getHtmlContent(): ?string
    {
        return $this->htmlContent;
    }

    public function setHtmlContent(?string $htmlContent): self
    {
        $this->htmlContent = $htmlContent;

        return $this;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): self
    {
        $this->altText = $altText;

        return $this;
    }

    public function getHeadline(): ?string
    {
        return $this->headline;
    }

    public function setHeadline(?string $headline): self
    {
        $this->headline = $headline;

        return $this;
    }

    public function getBodyText(): ?string
    {
        return $this->bodyText;
    }

    public function setBodyText(?string $bodyText): self
    {
        $this->bodyText = $bodyText;

        return $this;
    }

    public function getCtaLabel(): ?string
    {
        return $this->ctaLabel;
    }

    public function setCtaLabel(?string $ctaLabel): self
    {
        $this->ctaLabel = $ctaLabel;

        return $this;
    }

    public function getBgColor(): ?string
    {
        return $this->bgColor;
    }

    public function setBgColor(?string $bgColor): self
    {
        $this->bgColor = $bgColor;

        return $this;
    }

    public function getTextColor(): ?string
    {
        return $this->textColor;
    }

    public function setTextColor(?string $textColor): self
    {
        $this->textColor = $textColor;

        return $this;
    }

    public function getAccentColor(): ?string
    {
        return $this->accentColor;
    }

    public function setAccentColor(?string $accentColor): self
    {
        $this->accentColor = $accentColor;

        return $this;
    }

    public function getPresetKey(): ?string
    {
        return $this->presetKey;
    }

    public function setPresetKey(?string $presetKey): self
    {
        $this->presetKey = $presetKey;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
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

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): self
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getClickCount(): int
    {
        return $this->clickCount;
    }

    public function incrementClickCount(): self
    {
        ++$this->clickCount;

        return $this;
    }

    public function isScheduledNow(): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));
        if ($this->startsAt !== null && $this->asMadridLocal($this->startsAt) > $now) {
            return false;
        }
        if ($this->endsAt !== null && $this->asMadridLocal($this->endsAt) < $now) {
            return false;
        }

        return true;
    }

    public function isRenderable(): bool
    {
        if (!$this->isActive || !$this->isScheduledNow()) {
            return false;
        }

        return match ($this->resolveCreativeType()) {
            AdCreativeType::Image => $this->imageUrl !== null && $this->imageUrl !== '',
            AdCreativeType::Text, AdCreativeType::Generated => $this->headline !== null && $this->headline !== '',
            AdCreativeType::Html => $this->htmlContent !== null && $this->htmlContent !== '',
        };
    }

    /**
     * Las fechas de campaña se editan como hora local de Madrid (datetime-local).
     */
    private function asMadridLocal(\DateTimeImmutable $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value->format('Y-m-d H:i:s'), new \DateTimeZone('Europe/Madrid'));
    }

    /**
     * @param mixed $values
     *
     * @return list<int>
     */
    private function normalizeIdList(mixed $values): array
    {
        if (!\is_array($values)) {
            return [];
        }

        $ids = [];
        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
