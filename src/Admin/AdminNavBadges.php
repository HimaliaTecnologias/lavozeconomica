<?php

namespace App\Admin;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Platform\IngestedStory;
use App\Entity\Platform\Site;
use App\Entity\Platform\SitePublication;
use App\Entity\Site\Article;
use App\Entity\Site\ArticleComment;
use App\Entity\Site\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AdminNavBadges
{
    public function __construct(
        private readonly SiteEntityManagerFactory $siteEmFactory,
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
    ) {}

  /** @return array{ingest_pending: int, draft_articles: int, comments: int} */
    public function forSite(SiteContext $site): array
    {
        $em = $this->siteEmFactory->forContext($site);

        $draftArticles = (int) $em->getRepository(Article::class)->count(['status' => ArticleStatus::Draft]);
        $comments = (int) $em->getRepository(ArticleComment::class)->count(['isHidden' => false]);

        $ingestPending = 0;
        $platformSite = $this->platformEm->find(Site::class, $site->id);
        if ($platformSite !== null) {
            $stories = $this->platformEm->getRepository(IngestedStory::class)->findBy([], ['receivedAt' => 'DESC'], 200);
            $publications = $this->platformEm->getRepository(SitePublication::class)->findBy(['site' => $platformSite]);
            $publishedStoryIds = [];
            foreach ($publications as $publication) {
                $storyId = $publication->getIngestedStory()->getId();
                if ($storyId !== null) {
                    $publishedStoryIds[$storyId] = true;
                }
            }
            foreach ($stories as $story) {
                $storyId = $story->getId();
                if ($storyId !== null && !isset($publishedStoryIds[$storyId])) {
                    ++$ingestPending;
                }
            }
        }

        return [
            'ingest_pending' => $ingestPending,
            'draft_articles' => $draftArticles,
            'comments' => $comments,
        ];
    }
}
