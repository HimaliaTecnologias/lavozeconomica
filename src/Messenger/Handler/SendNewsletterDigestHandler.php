<?php

namespace App\Messenger\Handler;

use App\Core\Site\SiteContextResolver;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\NewsletterSubscriber;
use App\Editorial\NewsletterDigestBuilder;
use App\Editorial\NewsletterMailer;
use App\Messenger\SendNewsletterDigestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendNewsletterDigestHandler
{
    public function __construct(
        private readonly SiteContextResolver $siteResolver,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly NewsletterDigestBuilder $digestBuilder,
        private readonly NewsletterMailer $mailer,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(SendNewsletterDigestMessage $message): void
    {
        $context = $this->siteResolver->resolveByCode($message->siteCode);
        if ($context === null) {
            $this->logger->warning('Boletín: sitio no encontrado.', ['site_code' => $message->siteCode]);

            return;
        }

        $em = $this->siteEmFactory->forContext($context);
        $subscriber = $em->find(NewsletterSubscriber::class, $message->subscriberId);
        if ($subscriber === null) {
            return;
        }

        if ($subscriber->getStatus() !== \App\Entity\Site\Enum\NewsletterSubscriberStatus::Confirmed) {
            return;
        }

        $digest = $this->digestBuilder->buildForSubscriber($subscriber, $em);
        $articles = $digest['articles'];
        if ($articles === []) {
            return;
        }

        try {
            $this->mailer->sendDigest(
                $context,
                $subscriber,
                $articles,
                $digest['periodLabel'],
                $em,
            );
            $subscriber->markDigestSent();
            $em->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Boletín: envío fallido.', [
                'site_code' => $message->siteCode,
                'subscriber_id' => $message->subscriberId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
