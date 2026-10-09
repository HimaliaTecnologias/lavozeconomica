<?php

namespace App\Controller;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\AdBanner;
use App\Editorial\AdClickRecorder;
use App\Security\AbuseRateLimiter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicAdClickController extends AbstractController
{
    #[Route('/pub/ad/{id}/click', name: 'public_ad_click', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function click(
        int $id,
        Request $request,
        SiteContextHolder $holder,
        SiteEntityManagerFactory $siteEmFactory,
        AdClickRecorder $clickRecorder,
        AbuseRateLimiter $abuseRateLimiter,
    ): Response {
        $site = $holder->get();
        if ($site === null) {
            throw $this->createNotFoundException();
        }

        $abuseRateLimiter->enforceAdClick($request);

        $em = $siteEmFactory->forContext($site);
        $banner = $em->find(AdBanner::class, $id);
        if ($banner === null || !$banner->isRenderable()) {
            throw $this->createNotFoundException('Banner no disponible.');
        }

        $linkUrl = $banner->getLinkUrl();
        if ($linkUrl === null || $linkUrl === '') {
            throw $this->createNotFoundException('Este banner no tiene destino.');
        }

        $from = (string) $request->query->get('from', '');
        if ($from !== '' && !str_starts_with($from, '/')) {
            $from = '';
        }
        if (strlen($from) > 512) {
            $from = substr($from, 0, 512);
        }

        $slot = (string) $request->query->get('slot', '');
        if ($slot !== '' && !$banner->hasPlacement($slot)) {
            $slot = '';
        }

        $clickRecorder->record($banner, $from !== '' ? $from : null, $em, $slot !== '' ? $slot : null);

        return $this->redirect($linkUrl);
    }
}
