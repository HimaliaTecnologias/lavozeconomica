<?php

namespace App\Repository\Site;

use App\Entity\Site\Article;
use App\Entity\Site\Poll;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Poll>
 */
final class PollRepository extends EntityRepository
{
    /** @return list<Poll> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findWithOptions(int $id): ?Poll
    {
        return $this->createQueryBuilder('p')
            ->addSelect('o')
            ->leftJoin('p.options', 'o')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->orderBy('o.sortOrder', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return array<int, int> pollId => articleCount */
    public function countArticlesByPoll(): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(a.poll) AS pollId', 'COUNT(a.id) AS total')
            ->from(Article::class, 'a')
            ->where('a.poll IS NOT NULL')
            ->groupBy('a.poll')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['pollId']] = (int) $row['total'];
        }

        return $counts;
    }
}
