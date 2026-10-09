<?php

namespace App\Command;

use App\Core\Site\SiteContextResolver;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Platform\Site;
use App\Entity\Site\Enum\NewsletterFrequency;
use App\Entity\Site\NewsletterSubscriber;
use App\Messenger\SendNewsletterDigestMessage;
use App\Repository\Site\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'newsletter:send-digests',
    description: 'Encola envíos de boletín para suscriptores que toque según su periodicidad',
)]
final class NewsletterSendDigestsCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
        private readonly SiteContextResolver $contextResolver,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $queued = 0;

        foreach ($this->platformEm->getRepository(Site::class)->findAll() as $site) {
            $context = $this->contextResolver->resolveByCode($site->getCode());
            if ($context === null) {
                continue;
            }

            $em = $this->siteEmFactory->forContext($context);
            $repo = $em->getRepository(NewsletterSubscriber::class);
            if (!$repo instanceof NewsletterSubscriberRepository) {
                continue;
            }

            foreach (NewsletterFrequency::cases() as $frequency) {
                $threshold = new \DateTimeImmutable('-'.$frequency->periodSeconds().' seconds');
                $subscribers = $repo->findDueForDigest($frequency, $threshold);

                foreach ($subscribers as $subscriber) {
                    $subscriberId = $subscriber->getId();
                    if ($subscriberId === null) {
                        continue;
                    }

                    $this->messageBus->dispatch(new SendNewsletterDigestMessage($context->code, $subscriberId));
                    ++$queued;
                }
            }
        }

        if ($queued === 0) {
            $io->note('No hay boletines pendientes de envío.');
        } else {
            $io->success(sprintf('Encolados %d envío(s) de boletín.', $queued));
        }

        return Command::SUCCESS;
    }
}
