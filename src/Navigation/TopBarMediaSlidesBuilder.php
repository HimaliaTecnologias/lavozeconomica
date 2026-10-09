<?php

namespace App\Navigation;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use App\Markets\MarketFormatter;
use App\Markets\MarketService;
use App\Tv\TvProgrammingService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TopBarMediaSlidesBuilder
{
    private const MAX_MARKET_SLIDES = 2;
    private const MAX_TV_SLIDES = 4;

    public function __construct(
        private readonly SiteSettingsReader $settingsReader,
        private readonly MarketService $marketService,
        private readonly TvProgrammingService $tvProgrammingService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @return list<array{kind: string, url: string, label: string, cssClass: string}>
     */
    public function build(SiteContext $context): array
    {
        $slides = [];

        $marketsConfig = $this->settingsReader->getMarketConfig($context);
        if ($marketsConfig->enabled && $marketsConfig->isConfigured()) {
            $board = $this->marketService->getBoard($context);
            if ($board !== null && $board->indices !== []) {
                foreach (array_slice($board->indices, 0, self::MAX_MARKET_SLIDES) as $snapshot) {
                    $label = 'Bolsa · '.$snapshot->name;
                    if ($snapshot->changePercent !== null) {
                        $label .= ' · '.MarketFormatter::formatChangePercent($snapshot->changePercent);
                    }

                    $slides[] = [
                        'kind' => 'markets',
                        'url' => $this->urlGenerator->generate('public_markets'),
                        'label' => $label,
                        'cssClass' => 'top-bar-markets '.MarketFormatter::changeClass($snapshot->changePercent),
                    ];
                }
            }
        }

        $tvConfig = $this->settingsReader->getTvConfig($context);
        if ($tvConfig->enabled && $tvConfig->isConfigured()) {
            $schedule = $this->tvProgrammingService->getSchedule($context);
            $nowItems = $schedule?->nowOnTv() ?? [];

            if ($nowItems !== []) {
                foreach (array_slice($nowItems, 0, self::MAX_TV_SLIDES) as $item) {
                    $channel = $item['channel'];
                    $programme = $item['programme'];
                    $title = $programme->title;
                    if (mb_strlen($title) > 28) {
                        $title = mb_substr($title, 0, 25).'…';
                    }

                    $slides[] = [
                        'kind' => 'tv',
                        'url' => $this->urlGenerator->generate('public_tv'),
                        'label' => 'TV · '.$channel->name.' · '.$title,
                        'cssClass' => 'top-bar-tv',
                    ];
                }
            } else {
                $slides[] = [
                    'kind' => 'tv',
                    'url' => $this->urlGenerator->generate('public_tv'),
                    'label' => 'Guía TV',
                    'cssClass' => 'top-bar-tv',
                ];
            }
        }

        return $slides;
    }
}
