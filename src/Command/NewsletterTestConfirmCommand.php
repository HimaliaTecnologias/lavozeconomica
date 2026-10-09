<?php

namespace App\Command;

use App\Core\Site\SiteContextResolver;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Enum\NewsletterFrequency;
use App\Entity\Site\Enum\NewsletterSubscriberStatus;
use App\Entity\Site\NewsletterSubscriber;
use App\Editorial\NewsletterDigestBuilder;
use App\Editorial\NewsletterMailer;
use App\Editorial\NewsletterSubscriptionService;
use App\Repository\Site\NewsletterSubscriberRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'newsletter:test-confirm',
    description: 'Prueba el envío del email de confirmación (o digest) del boletín',
)]
final class NewsletterTestConfirmCommand extends Command
{
    public function __construct(
        private readonly SiteContextResolver $contextResolver,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly NewsletterSubscriptionService $subscriptionService,
        private readonly NewsletterDigestBuilder $digestBuilder,
        private readonly NewsletterMailer $newsletterMailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email del suscriptor de prueba')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Código del periódico', 'demo')
            ->addOption('frequency', null, InputOption::VALUE_REQUIRED, 'daily|weekly', 'weekly')
            ->addOption('digest', null, InputOption::VALUE_NONE, 'Enviar también un digest inmediato (confirma al suscriptor si hace falta)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $code = (string) $input->getOption('site');
        $email = trim((string) $input->getArgument('email'));
        $frequency = NewsletterFrequency::tryFrom((string) $input->getOption('frequency'))
            ?? NewsletterFrequency::Weekly;
        $sendDigest = (bool) $input->getOption('digest');

        $site = $this->contextResolver->resolveByCode($code);
        if ($site === null) {
            $io->error(sprintf('Periódico "%s" no encontrado.', $code));

            return Command::FAILURE;
        }

        $em = $this->siteEmFactory->forContext($site);

        if (!$sendDigest) {
            $io->writeln(sprintf('Suscribiendo %s y enviando confirmación (%s)…', $email, $site->name));
            try {
                $subscriber = $this->subscriptionService->subscribe($email, $frequency, [], $em, $site);
            } catch (\Throwable $e) {
                $io->error('Falló la suscripción/envío: '.$e->getMessage());

                return Command::FAILURE;
            }

            $io->success(sprintf(
                'OK. Suscriptor #%s estado=%s. Revisa el buzón (confirmación de boletín).',
                (string) $subscriber->getId(),
                $subscriber->getStatus()->value,
            ));

            return Command::SUCCESS;
        }

        $repo = $em->getRepository(NewsletterSubscriber::class);
        if (!$repo instanceof NewsletterSubscriberRepository) {
            $io->error('Repositorio de suscriptores no disponible.');

            return Command::FAILURE;
        }

        $subscriber = $repo->findByEmail($email);
        if ($subscriber === null) {
            $subscriber = $this->subscriptionService->subscribe($email, $frequency, [], $em, $site);
        }

        if ($subscriber->getStatus() !== NewsletterSubscriberStatus::Confirmed) {
            $subscriber->confirm();
            $em->flush();
        }

        $digest = $this->digestBuilder->buildForSubscriber($subscriber, $em);
        if ($digest['articles'] === []) {
            $io->warning('No hay artículos publicados en el periodo; no se envía digest.');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf(
            'Enviando digest (%d noticias) a %s…',
            \count($digest['articles']),
            $email,
        ));

        try {
            $this->newsletterMailer->sendDigest($site, $subscriber, $digest['articles'], $digest['periodLabel'], $em);
            $subscriber->markDigestSent();
            $em->flush();
        } catch (\Throwable $e) {
            $io->error('Falló el digest: '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Digest enviado correctamente.');

        return Command::SUCCESS;
    }
}
