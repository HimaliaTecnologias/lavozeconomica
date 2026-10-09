<?php

namespace App\Repository\Site;

use App\Entity\Site\WeatherWebcam;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<WeatherWebcam>
 */
final class WeatherWebcamRepository extends EntityRepository
{
    /** @return list<WeatherWebcam> */
    public function findEnabledOrdered(): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.enabled = true')
            ->orderBy('w.sortOrder', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<WeatherWebcam> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('w')
            ->orderBy('w.sortOrder', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
