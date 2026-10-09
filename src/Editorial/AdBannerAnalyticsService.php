<?php

namespace App\Editorial;

use App\Entity\Site\AdBanner;
use App\Repository\Site\AdBannerClickRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AdBannerAnalyticsService
{
    public function __construct(
        private readonly AdClickRecorder $clickRecorder,
    ) {}

    /**
     * @return array{
     *     days: int,
     *     from: \DateTimeImmutable,
     *     to: \DateTimeImmutable,
     *     totalClicks: int,
     *     previousClicks: int,
     *     clicksToday: int,
     *     dailySeries: list<array{date: string, label: string, clicks: int}>,
     *     maxDailyClicks: int,
     *     bannerStats: list<array{
     *         banner: AdBanner,
     *         clicksPeriod: int,
     *         clicksTotal: int
     *     }>,
     *     campaigns: list<array{
     *         banner: AdBanner,
     *         clicksInCampaign: int,
     *         status: string
     *     }>,
     *     activeBanners: int
     * }
     */
    public function buildDashboard(EntityManagerInterface $em, int $days): array
    {
        $days = max(7, min(90, $days));
        $to = new \DateTimeImmutable('tomorrow');
        $from = $to->modify('-'.$days.' days');
        $previousFrom = $from->modify('-'.$days.' days');

        $clickRepo = $this->clickRecorder->clickRepository($em);
        $banners = $em->getRepository(AdBanner::class)->findBy([], ['name' => 'ASC']);

        $dailyRaw = $clickRepo->countGroupedByDay($from, $to);
        $dailySeries = [];
        $maxDaily = 0;
        for ($i = $days - 1; $i >= 0; --$i) {
            $date = $to->modify('-'.($i + 1).' days');
            $key = $date->format('Y-m-d');
            $clicks = $dailyRaw[$key] ?? 0;
            $maxDaily = max($maxDaily, $clicks);
            $dailySeries[] = [
                'date' => $key,
                'label' => $date->format('d/m'),
                'clicks' => $clicks,
            ];
        }

        $clicksByBanner = $clickRepo->countGroupedByBanner($from, $to);
        $bannerStats = [];
        foreach ($banners as $banner) {
            $id = $banner->getId();
            $bannerStats[] = [
                'banner' => $banner,
                'clicksPeriod' => $id !== null ? ($clicksByBanner[$id] ?? 0) : 0,
                'clicksTotal' => $banner->getClickCount(),
            ];
        }

        usort($bannerStats, static fn (array $a, array $b): int => $b['clicksPeriod'] <=> $a['clicksPeriod']);

        $campaigns = [];
        foreach ($banners as $banner) {
            if ($banner->getStartsAt() === null && $banner->getEndsAt() === null) {
                continue;
            }

            $campaignFrom = $banner->getStartsAt() ?? $banner->getCreatedAt();
            $campaignTo = $banner->getEndsAt() ?? $to;
            $clicksInCampaign = $clickRepo->countForBannerInRange($banner, $campaignFrom, $campaignTo->modify('+1 day'));

            $status = 'programada';
            if ($banner->isActive() && $banner->isScheduledNow()) {
                $status = 'activa';
            } elseif ($banner->getEndsAt() !== null && $banner->getEndsAt() < new \DateTimeImmutable()) {
                $status = 'finalizada';
            } elseif (!$banner->isActive()) {
                $status = 'pausada';
            }

            $campaigns[] = [
                'banner' => $banner,
                'clicksInCampaign' => $clicksInCampaign,
                'status' => $status,
            ];
        }

        usort($campaigns, static fn (array $a, array $b): int => $b['clicksInCampaign'] <=> $a['clicksInCampaign']);

        $activeBanners = 0;
        foreach ($banners as $banner) {
            if ($banner->isRenderable()) {
                ++$activeBanners;
            }
        }

        $todayStart = new \DateTimeImmutable('today');
        $clicksToday = $clickRepo->countTotal($todayStart, $to);

        return [
            'days' => $days,
            'from' => $from,
            'to' => $to,
            'totalClicks' => $clickRepo->countTotal($from, $to),
            'previousClicks' => $clickRepo->countTotal($previousFrom, $from),
            'clicksToday' => $clicksToday,
            'dailySeries' => $dailySeries,
            'maxDailyClicks' => max(1, $maxDaily),
            'bannerStats' => $bannerStats,
            'campaigns' => $campaigns,
            'activeBanners' => $activeBanners,
        ];
    }
}
