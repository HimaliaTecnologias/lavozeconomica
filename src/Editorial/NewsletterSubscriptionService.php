<?php

namespace App\Editorial;

use App\Entity\Site\Category;
use App\Entity\Site\Enum\NewsletterFrequency;
use App\Entity\Site\NewsletterSubscriber;
use App\Repository\Site\CategoryRepository;
use App\Repository\Site\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NewsletterSubscriptionService
{
    public function __construct(
        private readonly NewsletterMailer $mailer,
    ) {}

    public function subscribe(
        string $email,
        NewsletterFrequency $frequency,
        array $categoryIds,
        EntityManagerInterface $em,
        \App\Core\Site\SiteContext $site,
    ): NewsletterSubscriber {
        $email = strtolower(trim($email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Introduce un correo electrónico válido.');
        }

        $repo = $this->subscriberRepository($em);
        $existing = $repo->findByEmail($email);

        $categories = $this->resolveCategories($categoryIds, $em);

        if ($existing !== null) {
            if ($existing->getStatus() === \App\Entity\Site\Enum\NewsletterSubscriberStatus::Unsubscribed) {
                $existing->setFrequency($frequency)->syncCategories($categories)->issueConfirmToken();
                $em->flush();
                $this->mailer->sendConfirmation($site, $existing, $em);

                return $existing;
            }

            $existing->setFrequency($frequency)->syncCategories($categories);
            if ($existing->getStatus() === \App\Entity\Site\Enum\NewsletterSubscriberStatus::Pending) {
                $existing->issueConfirmToken();
                $em->flush();
                $this->mailer->sendConfirmation($site, $existing, $em);

                return $existing;
            }

            $existing->confirm();
            $em->flush();

            return $existing;
        }

        $subscriber = new NewsletterSubscriber($email, $frequency);
        $subscriber->syncCategories($categories)->issueConfirmToken();
        $em->persist($subscriber);
        $em->flush();

        $this->mailer->sendConfirmation($site, $subscriber, $em);

        return $subscriber;
    }

    public function confirm(string $token, EntityManagerInterface $em): NewsletterSubscriber
    {
        $subscriber = $this->subscriberRepository($em)->findByConfirmToken($token);
        if ($subscriber === null || !$subscriber->isConfirmTokenValid($token)) {
            throw new \InvalidArgumentException('El enlace de confirmación no es válido o ha caducado.');
        }

        $subscriber->confirm();
        $em->flush();

        return $subscriber;
    }

    public function unsubscribe(string $token, EntityManagerInterface $em): NewsletterSubscriber
    {
        $subscriber = $this->subscriberRepository($em)->findByUnsubscribeToken($token);
        if ($subscriber === null) {
            throw new \InvalidArgumentException('Enlace de baja no válido.');
        }

        $subscriber->unsubscribe();
        $em->flush();

        return $subscriber;
    }

    public function updatePreferences(
        string $token,
        NewsletterFrequency $frequency,
        array $categoryIds,
        EntityManagerInterface $em,
    ): NewsletterSubscriber {
        $subscriber = $this->subscriberRepository($em)->findByUnsubscribeToken($token);
        if ($subscriber === null) {
            throw new \InvalidArgumentException('Suscriptor no encontrado.');
        }

        if ($subscriber->getStatus() === \App\Entity\Site\Enum\NewsletterSubscriberStatus::Unsubscribed) {
            throw new \InvalidArgumentException('Esta suscripción está cancelada.');
        }

        $categories = $this->resolveCategories($categoryIds, $em);
        $subscriber->setFrequency($frequency)->syncCategories($categories);
        $em->flush();

        return $subscriber;
    }

    /** @param list<int|string> $categoryIds @return list<Category> */
    private function resolveCategories(array $categoryIds, EntityManagerInterface $em): array
    {
        $categories = [];
        foreach ($categoryIds as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                continue;
            }
            $category = $em->find(Category::class, $id);
            if ($category !== null) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    private function subscriberRepository(EntityManagerInterface $em): NewsletterSubscriberRepository
    {
        $repo = $em->getRepository(NewsletterSubscriber::class);
        if (!$repo instanceof NewsletterSubscriberRepository) {
            throw new \RuntimeException('NewsletterSubscriberRepository no disponible.');
        }

        return $repo;
    }
}
