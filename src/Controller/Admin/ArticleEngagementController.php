<?php

namespace App\Controller\Admin;

use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Article;
use App\Entity\Site\ArticleComment;
use App\Editorial\PollVoteService;
use App\Repository\Site\ArticleCommentRepository;
use App\Repository\Site\ArticleRepository;
use App\Security\SiteAuthorizationChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/engagement')]
#[IsGranted('ROLE_USER')]
final class ArticleEngagementController extends AbstractAdminController
{
    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly PublicPageContextFactory $pageContextFactory,
        private readonly PollVoteService $pollVoteService,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('/comments', name: 'admin_engagement_comments', methods: ['GET'])]
    public function comments(): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMENTS);
        $em = $this->siteEmFactory->forContext($site);
        $repo = $em->getRepository(ArticleComment::class);
        $comments = $repo instanceof ArticleCommentRepository
            ? $repo->findRecentForAdmin(80)
            : [];

        return $this->render('admin/engagement/comments.html.twig', [
            'site' => $site,
            'comments' => $comments,
        ]);
    }

    #[Route('/comments/{id}/hide', name: 'admin_engagement_comment_hide', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function hideComment(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMENTS);
        $em = $this->siteEmFactory->forContext($site);
        $comment = $em->find(ArticleComment::class, $id);
        if ($comment === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('hide_comment'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $comment->hide();
        $em->flush();
        $this->addFlash('success', 'Comentario ocultado.');

        return $this->redirectToRoute('admin_engagement_comments');
    }

    #[Route('/comments/{id}/restore', name: 'admin_engagement_comment_restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restoreComment(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMENTS);
        $em = $this->siteEmFactory->forContext($site);
        $comment = $em->find(ArticleComment::class, $id);
        if ($comment === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('restore_comment'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $comment->restore();
        $em->flush();
        $this->addFlash('success', 'Comentario restaurado.');

        return $this->redirectToRoute('admin_engagement_comments');
    }

    #[Route('/articles/{id}/preview', name: 'admin_articles_preview', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function previewArticle(int $id, Request $request): Response
    {
        [$site] = $this->requireArticleEditorAccess();
        $pageContext = $this->pageContextFactory->build($site);
        $em = $this->siteEmFactory->forContext($site);
        $article = $em->find(Article::class, $id);
        if ($article === null) {
            throw $this->createNotFoundException();
        }

        $repo = $em->getRepository(Article::class);
        $category = $article->getCategory();
        $categoryId = $category?->getId();
        $articleId = $article->getId();

        $categoryArticles = [];
        $manualRelatedArticles = [];
        $relatedArticles = [];
        $latestArticles = [];
        $popularArticles = [];
        if ($repo instanceof ArticleRepository && $articleId !== null) {
            $categoryArticles = $categoryId !== null
                ? $repo->findPublishedByCategory($categoryId, 8, $articleId)
                : [];
            $manualRelatedArticles = $repo->findManualRelatedPublished($articleId, 8);
            $excludeForInterest = [$articleId];
            foreach ($manualRelatedArticles as $manualArticle) {
                $manualId = $manualArticle->getId();
                if ($manualId !== null) {
                    $excludeForInterest[] = $manualId;
                }
            }
            foreach ($categoryArticles as $categoryArticle) {
                $categoryArticleId = $categoryArticle->getId();
                if ($categoryArticleId !== null) {
                    $excludeForInterest[] = $categoryArticleId;
                }
            }
            $relatedArticles = $repo->findPublishedRelated(
                $categoryId,
                $articleId,
                8,
                $excludeForInterest,
            );
            if (count($relatedArticles) < 8) {
                $exclude = $excludeForInterest;
                foreach ($relatedArticles as $item) {
                    $id = $item->getId();
                    if ($id !== null) {
                        $exclude[] = $id;
                    }
                }
                $relatedArticles = [
                    ...$relatedArticles,
                    ...$repo->findPublishedRelatedExcluding(8 - count($relatedArticles), $exclude, null),
                ];
            }
            $latestArticles = $repo->findPublishedLatest(8, $articleId);
            $popularArticles = $repo->findPublishedMostViewed(8, $articleId);
        }

        $poll = $article->getPoll();
        $pollVoted = false;
        if ($poll !== null) {
            $pollVoted = $this->pollVoteService->hasVoted($poll, $this->pollVoteService->resolveVoterKey($request), $em);
        }

        return $this->render('public/article/show.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'article' => $article,
            'pollVoted' => $pollVoted,
            'comments' => [],
            'ratingSummary' => ['average' => 0.0, 'count' => 0],
            'userRating' => null,
            'categoryArticles' => $categoryArticles,
            'categoryFeedItems' => $categoryArticles,
            'categoryFeedHasMore' => false,
            'categoryFeedTotal' => count($categoryArticles),
            'manualRelatedArticles' => $manualRelatedArticles,
            'relatedArticles' => $relatedArticles,
            'latestArticles' => $latestArticles,
            'popularArticles' => $popularArticles,
            'previewMode' => true,
        ]);
    }
}
