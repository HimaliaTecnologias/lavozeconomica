<?php

namespace App\Repository\Site;

use App\Entity\Site\Article;
use App\Entity\Site\Category;
use App\Entity\Site\Enum\ArticleStatus;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Category>
 */
final class CategoryRepository extends EntityRepository
{
    /** @return list<Category> */
    public function findRootsForAdmin(): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('children', 'grandchildren')
            ->leftJoin('c.children', 'children')
            ->leftJoin('children.children', 'grandchildren')
            ->where('c.parent IS NULL')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->addOrderBy('children.sortOrder', 'ASC')
            ->addOrderBy('children.name', 'ASC')
            ->addOrderBy('grandchildren.sortOrder', 'ASC')
            ->addOrderBy('grandchildren.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Category> */
    public function findNavRoots(): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('children')
            ->leftJoin('c.children', 'children', 'WITH', 'children.showInNav = true')
            ->where('c.parent IS NULL')
            ->andWhere('c.showInNav = true')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->addOrderBy('children.sortOrder', 'ASC')
            ->addOrderBy('children.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<int> */
    public function collectDescendantIds(Category $category): array
    {
        $ids = [];
        foreach ($category->getChildren() as $child) {
            $childId = $child->getId();
            if ($childId !== null) {
                $ids[] = $childId;
                $ids = array_merge($ids, $this->collectDescendantIds($child));
            }
        }

        return $ids;
    }

    /** @return list<int> */
    public function collectSelfAndDescendantIds(Category $category): array
    {
        $id = $category->getId();
        if ($id === null) {
            return [];
        }

        return array_merge([$id], $this->collectDescendantIds($category));
    }

    public function findOneBySlugWithRelations(string $slug): ?Category
    {
        return $this->createQueryBuilder('c')
            ->addSelect('parent', 'children')
            ->leftJoin('c.parent', 'parent')
            ->leftJoin('c.children', 'children')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->orderBy('children.sortOrder', 'ASC')
            ->addOrderBy('children.name', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isSlugTaken(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('c.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return array<int, int> */
    public function countPublishedArticlesByCategory(): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(a.category) AS categoryId', 'COUNT(a.id) AS total')
            ->from(Article::class, 'a')
            ->where('a.status = :status')
            ->andWhere('a.category IS NOT NULL')
            ->setParameter('status', ArticleStatus::Published)
            ->groupBy('a.category')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['categoryId']] = (int) $row['total'];
        }

        return $counts;
    }

    /** @return list<Category> */
    public function findParentOptions(?Category $exclude = null): array
    {
        $all = $this->createQueryBuilder('c')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        if ($exclude === null) {
            return $all;
        }

        $filtered = [];
        foreach ($all as $category) {
            if ($category->getId() === $exclude->getId() || $category->isDescendantOf($exclude)) {
                continue;
            }
            if ($category->getDepth() >= 2) {
                continue;
            }
            $filtered[] = $category;
        }

        return $filtered;
    }
}
