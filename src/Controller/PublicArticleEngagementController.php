<?php

namespace App\Controller;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Platform\User;
use App\Entity\Site\ArticleComment;
use App\Entity\Site\ArticleRating;
use App\Editorial\CommunityRichTextSanitizer;
use App\Repository\Site\ArticleRatingRepository;
use App\Repository\Site\ArticleRepository;
use App\Security\AbuseRateLimiter;
use App\Security\SiteAuthorizationChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PublicArticleEngagementController extends AbstractController
{
    #[Route('/noticias/{slug}/comentar', name: 'public_article_comment', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function comment(
        string $slug,
        Request $request,
        SiteContextHolder $holder,
        SiteEntityManagerFactory $siteEmFactory,
        SiteAuthorizationChecker $authorizationChecker,
        CommunityRichTextSanitizer $richTextSanitizer,
        AbuseRateLimiter $abuseRateLimiter,
    ): Response {
        $site = $holder->require();
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$authorizationChecker->canEngageAsReader($user, $site)) {
            throw $this->createAccessDeniedException('No puedes comentar en este periódico.');
        }

        if (!$this->isCsrfTokenValid('article_comment_'.$slug, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $userId = $user->getId();
        if ($userId === null) {
            throw $this->createAccessDeniedException();
        }

        $abuseRateLimiter->enforceUgc($request, 'comment', (string) $userId);

        $em = $siteEmFactory->forContext($site);
        $article = $this->articleRepository($em)->findPublishedBySlug($slug);
        if ($article === null) {
            throw $this->createNotFoundException('Noticia no encontrada.');
        }

        $rawBody = trim((string) $request->request->get('body', ''));
        $bodyLength = $richTextSanitizer->plainTextLength($rawBody);
        if ($richTextSanitizer->isEffectivelyEmpty($rawBody) || $bodyLength > 2000) {
            $this->addFlash('error', 'El comentario debe tener entre 1 y 2000 caracteres.');

            return $this->redirectToRoute('public_article_show', ['slug' => $slug]);
        }

        $body = $richTextSanitizer->sanitize($rawBody);
        $comment = new ArticleComment($article, $userId, $user->getName(), $body);

        // Hold link-heavy comments for moderation (common bot / spam pattern).
        if ($this->looksLikeSpam($body, $bodyLength)) {
            $comment->hide();
            $em->persist($comment);
            $em->flush();
            $this->addFlash('success', 'Comentario recibido. Se publicará tras revisión del equipo.');

            return $this->redirectToRoute('public_article_show', ['slug' => $slug]);
        }

        $em->persist($comment);
        $em->flush();

        $this->addFlash('success', 'Comentario publicado.');

        return $this->redirectToRoute('public_article_show', ['slug' => $slug]);
    }

    #[Route('/noticias/{slug}/valorar', name: 'public_article_rate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function rate(
        string $slug,
        Request $request,
        SiteContextHolder $holder,
        SiteEntityManagerFactory $siteEmFactory,
        SiteAuthorizationChecker $authorizationChecker,
        AbuseRateLimiter $abuseRateLimiter,
    ): Response {
        $site = $holder->require();
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$authorizationChecker->canEngageAsReader($user, $site)) {
            throw $this->createAccessDeniedException('No puedes valorar en este periódico.');
        }

        if (!$this->isCsrfTokenValid('article_rate_'.$slug, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $userId = $user->getId();
        if ($userId === null) {
            throw $this->createAccessDeniedException();
        }

        $abuseRateLimiter->enforceUgc($request, 'rate', (string) $userId);

        $em = $siteEmFactory->forContext($site);
        $article = $this->articleRepository($em)->findPublishedBySlug($slug);
        if ($article === null) {
            throw $this->createNotFoundException('Noticia no encontrada.');
        }

        $score = (int) $request->request->get('score', 0);
        if ($score < 1 || $score > 5) {
            $this->addFlash('error', 'Selecciona una valoración entre 1 y 5 estrellas.');

            return $this->redirectToRoute('public_article_show', ['slug' => $slug]);
        }

        $ratingRepo = $this->ratingRepository($em);
        $existing = $ratingRepo->findForUser($article, $userId);
        if ($existing !== null) {
            $existing->setScore($score);
        } else {
            $em->persist(new ArticleRating($article, $userId, $score));
        }
        $em->flush();

        $this->addFlash('success', 'Valoración guardada.');

        return $this->redirectToRoute('public_article_show', ['slug' => $slug]);
    }

    private function looksLikeSpam(string $body, int $plainLength): bool
    {
        $linkCount = preg_match_all('/https?:\/\//i', $body) ?: 0;
        if ($linkCount >= 2) {
            return true;
        }
        if ($linkCount >= 1 && $plainLength < 40) {
            return true;
        }

        return false;
    }

    private function articleRepository(EntityManagerInterface $em): ArticleRepository
    {
        $repo = $em->getRepository(\App\Entity\Site\Article::class);
        if (!$repo instanceof ArticleRepository) {
            throw new \RuntimeException('ArticleRepository no disponible.');
        }

        return $repo;
    }

    private function ratingRepository(EntityManagerInterface $em): ArticleRatingRepository
    {
        $repo = $em->getRepository(ArticleRating::class);
        if (!$repo instanceof ArticleRatingRepository) {
            throw new \RuntimeException('ArticleRatingRepository no disponible.');
        }

        return $repo;
    }
}
