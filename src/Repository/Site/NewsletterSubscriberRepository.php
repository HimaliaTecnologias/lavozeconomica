<?php

namespace App\Repository\Site;

use App\Entity\Site\Enum\NewsletterFrequency;
use App\Entity\Site\Enum\NewsletterSubscriberStatus;
use App\Entity\Site\NewsletterSubscriber;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<NewsletterSubscriber>
 */
final class NewsletterSubscriberRepository extends EntityRepository
{
    public function findByEmail(string $email): ?NewsletterSubscriber
    {
        return $this->findOneBy(['email' => strtolower(trim($email))]);
    }

    public function findByConfirmToken(string $token): ?NewsletterSubscriber
    {
        return $this->findOneBy(['confirmToken' => $token]);
    }

    public function findByUnsubscribeToken(string $token): ?NewsletterSubscriber
    {
        return $this->findOneBy(['unsubscribeToken' => $token]);
    }

    /**
     * @return list<NewsletterSubscriber>
     */
    public function findDueForDigest(NewsletterFrequency $frequency, \DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.frequency = :frequency')
            ->andWhere('s.lastDigestSentAt IS NULL OR s.lastDigestSentAt < :threshold')
            ->setParameter('status', NewsletterSubscriberStatus::Confirmed)
            ->setParameter('frequency', $frequency)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /** @return list<NewsletterSubscriber> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(NewsletterSubscriberStatus $status): int
    {
        return (int) $this->count(['status' => $status]);
    }
}
