<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\ArticleComment;
use App\Entity\Site\ClassifiedAd;
use App\Entity\Site\ForumPost;
use App\Entity\Site\ForumTopic;
use App\Repository\Site\ArticleCommentRepository;
use App\Repository\Site\ClassifiedAdRepository;
use App\Repository\Site\ForumPostRepository;
use App\Repository\Site\ForumTopicRepository;

final class UserAccountContentService
{
    public function __construct(
        private readonly SiteEntityManagerFactory $siteEmFactory,
    ) {}

    /** @return array{forumTopics: int, forumPosts: int, comments: int, classifieds: int} */
    public function countActivity(SiteContext $context, int $userId): array
    {
        $em = $this->siteEmFactory->forContext($context);

        return [
            'forumTopics' => $this->forumTopicRepo($em)->countByUserId($userId),
            'forumPosts' => $this->forumPostRepo($em)->countByUserId($userId),
            'comments' => $this->articleCommentRepo($em)->countByUserId($userId),
            'classifieds' => $this->classifiedAdRepo($em)->countByUserId($userId),
        ];
    }

    /** @return list<ForumTopic> */
    public function forumTopics(SiteContext $context, int $userId, int $limit = 50): array
    {
        return $this->forumTopicRepo($this->siteEmFactory->forContext($context))->findByUserId($userId, $limit);
    }

    /** @return list<ForumPost> */
    public function forumPosts(SiteContext $context, int $userId, int $limit = 50): array
    {
        return $this->forumPostRepo($this->siteEmFactory->forContext($context))->findByUserId($userId, $limit);
    }

    /** @return list<ArticleComment> */
    public function articleComments(SiteContext $context, int $userId, int $limit = 50): array
    {
        return $this->articleCommentRepo($this->siteEmFactory->forContext($context))->findByUserId($userId, $limit);
    }

    /** @return list<ClassifiedAd> */
    public function classifiedAds(SiteContext $context, int $userId, int $limit = 50): array
    {
        return $this->classifiedAdRepo($this->siteEmFactory->forContext($context))->findByUserId($userId, $limit);
    }

    private function forumTopicRepo(\Doctrine\ORM\EntityManagerInterface $em): ForumTopicRepository
    {
        $repo = $em->getRepository(ForumTopic::class);
        if (!$repo instanceof ForumTopicRepository) {
            throw new \RuntimeException('ForumTopicRepository no disponible.');
        }

        return $repo;
    }

    private function forumPostRepo(\Doctrine\ORM\EntityManagerInterface $em): ForumPostRepository
    {
        $repo = $em->getRepository(ForumPost::class);
        if (!$repo instanceof ForumPostRepository) {
            throw new \RuntimeException('ForumPostRepository no disponible.');
        }

        return $repo;
    }

    private function articleCommentRepo(\Doctrine\ORM\EntityManagerInterface $em): ArticleCommentRepository
    {
        $repo = $em->getRepository(ArticleComment::class);
        if (!$repo instanceof ArticleCommentRepository) {
            throw new \RuntimeException('ArticleCommentRepository no disponible.');
        }

        return $repo;
    }

    private function classifiedAdRepo(\Doctrine\ORM\EntityManagerInterface $em): ClassifiedAdRepository
    {
        $repo = $em->getRepository(ClassifiedAd::class);
        if (!$repo instanceof ClassifiedAdRepository) {
            throw new \RuntimeException('ClassifiedAdRepository no disponible.');
        }

        return $repo;
    }
}
