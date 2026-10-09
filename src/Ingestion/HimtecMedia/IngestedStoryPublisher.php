<?php

namespace App\Ingestion\HimtecMedia;

use App\Cache\PublicArticleCache;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Platform\Enum\SitePublicationStatus;
use App\Entity\Platform\IngestedStory;
use App\Entity\Platform\Site;
use App\Entity\Platform\SitePublication;
use App\Entity\Site\Article;
use App\Entity\Site\Category;
use App\Entity\Site\Enum\ArticleStatus;
use App\Editorial\ArticleFeaturedImageGuard;
use App\Editorial\ArticleImageEnhancementDispatcher;
use App\Editorial\ArticleImageSyncService;
use App\Editorial\ArticleImageUrlExtractor;
use App\Editorial\ArticleTagSyncService;
use App\Editorial\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class IngestedStoryPublisher
{
    private const FALLBACK_CATEGORY_NAME = 'General';
    private const FALLBACK_CATEGORY_SLUG = 'general';

    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly SlugGenerator $slugGenerator,
        private readonly ArticleImageUrlExtractor $imageUrlExtractor,
        private readonly ArticleImageSyncService $imageSyncService,
        private readonly ArticleImageEnhancementDispatcher $imageEnhancementDispatcher,
        private readonly ArticleTagSyncService $tagSyncService,
        private readonly ArticleFeaturedImageGuard $featuredImageGuard,
        private readonly PublicArticleCache $articleCache,
    ) {}

    public function publishForSite(
        SiteContext $context,
        IngestedStory $story,
        ?\DateTimeImmutable $scheduledAt = null,
    ): ?Article {
        return $this->publishOutcome($context, $story, $scheduledAt)->article;
    }

    public function publishOutcome(
        SiteContext $context,
        IngestedStory $story,
        ?\DateTimeImmutable $scheduledAt = null,
    ): IngestPublishOutcome {
        $site = $this->platformEm->find(Site::class, $context->id);
        if ($site === null) {
            throw new \RuntimeException('Sitio de plataforma no encontrado.');
        }

        $existingPublication = $this->platformEm->getRepository(SitePublication::class)->findOneBy([
            'site' => $site,
            'ingestedStory' => $story,
        ]);
        if ($existingPublication !== null && $existingPublication->getArticleId() !== null) {
            return new IngestPublishOutcome(
                $this->findArticleById($context, $existingPublication->getArticleId()),
                false,
            );
        }

        $siteEm = $this->siteEmFactory->forContext($context);
        $raw = $story->getRawPayload();

        $slug = $this->uniqueSlug($siteEm, $story->getTitle(), $story->getExternalId());
        $body = $story->getBodyHtml() !== '' ? $story->getBodyHtml() : '<p></p>';
        $now = new \DateTimeImmutable();
        $deferUntil = $scheduledAt !== null && $scheduledAt > $now ? $scheduledAt : null;
        // Empezar en borrador; se publica/programa solo si la imagen destacada es usable.
        $article = new Article(
            $story->getTitle(),
            $body,
            $slug,
            ArticleStatus::Draft,
        );

        $lead = isset($raw['resumen']) ? trim((string) $raw['resumen']) : '';
        if ($lead !== '') {
            $article->setLead($lead);
        }

        $article->setCategory($this->resolveCategory($siteEm, $raw));

        if ($story->getId() !== null) {
            $article->linkIngestedStory($story->getId(), $story->getExternalId());
        }

        $siteEm->persist($article);

        $images = $this->imageUrlExtractor->fromRawPayload($raw);
        if ($images !== []) {
            $this->imageSyncService->syncFromExtracted($siteEm, $article, $images);
        }

        $this->tagSyncService->syncForArticle($siteEm, $article);

        // Misma regla que el badge «Revisar imagen» del listado: sin imagen usable → no publicar.
        $imageIssue = $this->featuredImageGuard->getIssue($article);
        $keepAsDraft = $imageIssue !== null;

        if (!$keepAsDraft) {
            if ($deferUntil !== null) {
                $article->schedule($deferUntil);
            } else {
                $article->publish();
            }
        }

        $siteEm->flush();
        if (!$keepAsDraft && $deferUntil === null) {
            $this->articleCache->invalidateSite($context);
        }

        if (!$keepAsDraft) {
            $this->imageEnhancementDispatcher->dispatchForArticle($context, $article);
        }

        $publicationStatus = match (true) {
            $keepAsDraft => SitePublicationStatus::Review,
            $deferUntil !== null => SitePublicationStatus::Scheduled,
            default => SitePublicationStatus::Published,
        };
        $publication = new SitePublication($site, $story, $publicationStatus);
        $publication->setArticleId($article->getId());
        $publication->setAutoPublished(!$keepAsDraft);
        if (!$keepAsDraft && $deferUntil === null) {
            $publication->markPublished();
        }

        $story->markProcessed();
        $this->platformEm->persist($publication);
        $this->platformEm->flush();

        return new IngestPublishOutcome($article, true);
    }

    private function findExistingArticle(SiteContext $context, IngestedStory $story): ?Article
    {
        $site = $this->platformEm->find(Site::class, $context->id);
        if ($site === null) {
            return null;
        }

        $publication = $this->platformEm->getRepository(SitePublication::class)->findOneBy([
            'site' => $site,
            'ingestedStory' => $story,
        ]);

        if ($publication?->getArticleId() === null) {
            return null;
        }

        return $this->findArticleById($context, $publication->getArticleId());
    }

    private function findArticleById(SiteContext $context, int $articleId): ?Article
    {
        return $this->siteEmFactory->forContext($context)->find(Article::class, $articleId);
    }

    private function uniqueSlug(EntityManagerInterface $siteEm, string $title, string $externalId): string
    {
        $base = $this->slugGenerator->fromTitle($title);
        $repo = $siteEm->getRepository(Article::class);

        if ($repo->findOneBy(['slug' => $base]) === null) {
            return $base;
        }

        $slug = $base.'-'.$this->slugGenerator->fromTitle($externalId);
        if ($repo->findOneBy(['slug' => $slug]) === null) {
            return $slug;
        }

        return $base.'-'.$externalId;
    }

    /**
     * Usa la categoría del payload si existe en el sitio (por id, slug o nombre);
     * si no hay (o no coincide), «General».
     *
     * HimtecMedia envía «categoria» como entero = id de categoría del CMS destino.
     *
     * @param array<string, mixed> $raw
     */
    private function resolveCategory(EntityManagerInterface $siteEm, array $raw): Category
    {
        $hint = $this->categoryHintFromPayload($raw);
        if ($hint !== null) {
            $matched = $this->findCategoryByHint($siteEm, $hint);
            if ($matched !== null) {
                return $matched;
            }
        }

        return $this->findOrCreateGeneralCategory($siteEm);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function categoryHintFromPayload(array $raw): ?string
    {
        foreach (['categoria', 'category', 'categoría', 'seccion', 'sección', 'section', 'rubrica', 'channel', 'canal'] as $key) {
            if (!isset($raw[$key])) {
                continue;
            }
            $value = $raw[$key];
            if (\is_array($value)) {
                $value = $value['id'] ?? $value['name'] ?? $value['nombre'] ?? $value['title'] ?? $value['slug'] ?? null;
            }
            if (\is_int($value) || \is_float($value)) {
                $hint = (string) (int) $value;
            } else {
                $hint = trim((string) $value);
            }
            if ($hint !== '' && $hint !== '0') {
                return $hint;
            }
        }

        $classification = $raw['classification'] ?? null;
        if (\is_array($classification)) {
            foreach (['categoria', 'category', 'id', 'name', 'nombre', 'slug'] as $key) {
                if (!isset($classification[$key])) {
                    continue;
                }
                $nested = $classification[$key];
                if (\is_int($nested) || \is_float($nested)) {
                    $hint = (string) (int) $nested;
                } else {
                    $hint = trim((string) $nested);
                }
                if ($hint !== '' && $hint !== '0') {
                    return $hint;
                }
            }
        }

        return null;
    }

    private function findCategoryByHint(EntityManagerInterface $siteEm, string $hint): ?Category
    {
        $repo = $siteEm->getRepository(Category::class);

        // HimtecMedia manda el id numérico de la categoría del sitio (CMS destino).
        if (ctype_digit($hint) && (int) $hint > 0) {
            $byId = $repo->find((int) $hint);
            if ($byId instanceof Category) {
                return $byId;
            }
        }

        $slug = $this->slugGenerator->fromTitle($hint);
        $bySlug = $repo->findOneBy(['slug' => $slug]);
        if ($bySlug instanceof Category) {
            return $bySlug;
        }

        foreach ($repo->findAll() as $category) {
            if (!$category instanceof Category) {
                continue;
            }
            if (strcasecmp($category->getName(), $hint) === 0 || strcasecmp($category->getSlug(), $slug) === 0) {
                return $category;
            }
        }

        return null;
    }

    private function findOrCreateGeneralCategory(EntityManagerInterface $siteEm): Category
    {
        $repo = $siteEm->getRepository(Category::class);
        $existing = $repo->findOneBy(['slug' => self::FALLBACK_CATEGORY_SLUG]);
        if ($existing instanceof Category) {
            return $existing;
        }

        foreach ($repo->findAll() as $category) {
            if ($category instanceof Category && strcasecmp($category->getName(), self::FALLBACK_CATEGORY_NAME) === 0) {
                return $category;
            }
        }

        $category = new Category(self::FALLBACK_CATEGORY_NAME, self::FALLBACK_CATEGORY_SLUG);
        $siteEm->persist($category);

        return $category;
    }
}
