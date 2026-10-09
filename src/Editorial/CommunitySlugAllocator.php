<?php

namespace App\Editorial;

use App\Entity\Site\ClassifiedAd;
use App\Entity\Site\ForumTopic;
use Doctrine\ORM\EntityManagerInterface;

final class CommunitySlugAllocator
{
    public function __construct(
        private readonly SlugGenerator $slugGenerator,
    ) {}

    public function forClassifiedAd(EntityManagerInterface $em, string $title): string
    {
        return $this->unique($em, ClassifiedAd::class, $this->slugGenerator->fromTitle($title));
    }

    public function forForumTopic(EntityManagerInterface $em, string $title): string
    {
        return $this->unique($em, ForumTopic::class, $this->slugGenerator->fromTitle($title));
    }

  /**
   * @param class-string $entityClass
   */
    private function unique(EntityManagerInterface $em, string $entityClass, string $base): string
    {
        $slug = $base;
        $suffix = 2;
        while ($em->getRepository($entityClass)->findOneBy(['slug' => $slug]) !== null) {
            $slug = $base.'-'.$suffix;
            ++$suffix;
        }

        return $slug;
    }
}
