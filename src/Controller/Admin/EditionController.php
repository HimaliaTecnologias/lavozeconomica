<?php

namespace App\Controller\Admin;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Editorial\EditionPdfDownloadRecorder;
use App\Repository\Site\ArticleRepository;
use App\Security\SiteAuthorizationChecker;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/ediciones')]
#[IsGranted('ROLE_USER')]
final class EditionController extends AbstractAdminController
{
    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly EditionPdfDownloadRecorder $pdfDownloadRecorder,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('', name: 'admin_editions_index', methods: ['GET'])]
    public function index(): Response
    {
        [$site, $user] = $this->requireArticleEditorAccess();

        $tz = new \DateTimeZone('Europe/Madrid');
        $today = new \DateTimeImmutable('today', $tz);
        $from = $today->modify('-60 days');

        $repo = $this->articleRepository($this->siteEmFactory->forContext($site));
        $activeDates = $repo->findPublishedDatesBetween($from, $today->modify('+1 day'));
        usort($activeDates, static fn (string $a, string $b): int => strcmp($b, $a));

        $downloadCounts = $this->pdfDownloadRecorder->countsForDates($site, $activeDates);

        $rows = [];
        foreach ($activeDates as $dateIso) {
            $day = new \DateTimeImmutable($dateIso.' 00:00:00', $tz);
            $rows[] = [
                'dateIso' => $dateIso,
                'dateLabel' => $day->format('d/m/Y'),
                'articleCount' => $repo->countPublishedOnDate($day),
                'pdfDownloads' => $downloadCounts[$dateIso] ?? 0,
                'publicUrl' => $this->urlGenerator->generate('public_edition_date', ['date' => $dateIso]),
                'pdfUrl' => $this->urlGenerator->generate('public_edition_pdf_date', ['date' => $dateIso]),
            ];
        }

        return $this->render('admin/edition/index.html.twig', [
            'site' => $site,
            'user' => $user,
            'rows' => $rows,
        ]);
    }

    private function articleRepository(\Doctrine\ORM\EntityManagerInterface $em): ArticleRepository
    {
        $repo = $em->getRepository(\App\Entity\Site\Article::class);
        if (!$repo instanceof ArticleRepository) {
            throw new \RuntimeException('ArticleRepository no disponible.');
        }

        return $repo;
    }
}
