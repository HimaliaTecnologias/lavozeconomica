<?php

namespace App\Controller;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Core\Site\SiteSettingsReader;
use App\Editorial\SitePageRegistry;
use App\Entity\Site\Article;
use App\Entity\Site\Category;
use App\Repository\Site\ArticleRepository;
use App\Repository\Site\CategoryRepository;
use App\Horoscope\HoroscopeSignRegistry;
use App\Seo\PublicUrlFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicSeoController extends AbstractController
{
    public function __construct(
        private readonly PublicUrlFactory $publicUrls,
    ) {}

    #[Route('/robots.txt', name: 'public_robots', methods: ['GET'])]
    public function robots(
        SiteContextHolder $holder,
        SiteSettingsReader $settingsReader,
    ): Response {
        $site = $holder->get();
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /gestor/',
            'Disallow: /admin/',
            'Disallow: /panel/',
            'Disallow: /cuenta/',
            'Disallow: /install/',
            'Disallow: /api/',
            'Disallow: /health/',
            'Disallow: /buscar/pagina/',
        ];

        if ($site !== null) {
            foreach ($settingsReader->getSeoConfig($site)->robotsExtraLines() as $extraLine) {
                $lines[] = $extraLine;
            }
            $lines[] = 'Sitemap: '.$this->publicUrls->normalize(
                $this->generateUrl('public_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL),
            );
            $lines[] = 'Sitemap: '.$this->publicUrls->normalize(
                $this->generateUrl('public_sitemap_news', [], UrlGeneratorInterface::ABSOLUTE_URL),
            );
        } else {
            $lines[] = 'Sitemap: '.$this->publicUrls->absolutePath('/sitemap.xml');
            $lines[] = 'Sitemap: '.$this->publicUrls->absolutePath('/sitemap-news.xml');
        }

        return $this->seoTextResponse(implode("\n", $lines)."\n");
    }

    #[Route('/sitemap.xml', name: 'public_sitemap', methods: ['GET'])]
    public function sitemap(
        SiteContextHolder $holder,
        SiteEntityManagerFactory $siteEmFactory,
    ): Response {
        $site = $holder->get();
        if ($site === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $em = $siteEmFactory->forContext($site);
        $articleRepo = $em->getRepository(Article::class);
        $categoryRepo = $em->getRepository(Category::class);
        $today = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->format('Y-m-d');

        $urls = [];
        $urls[] = $this->urlEntry($this->generateUrl('home', [], UrlGeneratorInterface::ABSOLUTE_URL), $today, 'hourly', '1.0');

        if ($categoryRepo instanceof CategoryRepository) {
            foreach ($categoryRepo->findNavRoots() as $category) {
                $urls[] = $this->urlEntry(
                    $this->generateUrl('public_category_show', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                    $today,
                    'daily',
                    '0.8',
                );
                foreach ($category->getChildren() as $child) {
                    $urls[] = $this->urlEntry(
                        $this->generateUrl('public_category_show', ['slug' => $child->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                        $today,
                        'daily',
                        '0.7',
                    );
                }
            }
        }

        if ($articleRepo instanceof ArticleRepository) {
            foreach ($articleRepo->findPublishedLatest(5000) as $article) {
                $lastMod = $article->getUpdatedAt() ?? $article->getPublishedAt();
                $urls[] = $this->urlEntry(
                    $this->generateUrl('public_article_show', ['slug' => $article->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                    $lastMod?->format('Y-m-d'),
                    'daily',
                    '0.9',
                );
            }
        }

        foreach (SitePageRegistry::pages() as $slug => $definition) {
            $urls[] = $this->urlEntry(
                $this->generateUrl('public_page_show', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL),
                null,
                'monthly',
                '0.5',
            );
        }

        $urls[] = $this->urlEntry($this->generateUrl('public_weather', [], UrlGeneratorInterface::ABSOLUTE_URL), $today, 'hourly', '0.6');
        $urls[] = $this->urlEntry($this->generateUrl('public_markets', [], UrlGeneratorInterface::ABSOLUTE_URL), $today, 'hourly', '0.6');
        $urls[] = $this->urlEntry($this->generateUrl('public_tv', [], UrlGeneratorInterface::ABSOLUTE_URL), $today, 'hourly', '0.6');
        $urls[] = $this->urlEntry($this->generateUrl('public_games', [], UrlGeneratorInterface::ABSOLUTE_URL), $today, 'daily', '0.5');
        $urls[] = $this->urlEntry($this->generateUrl('public_games_sudoku', [], UrlGeneratorInterface::ABSOLUTE_URL), $today, 'daily', '0.5');
        $urls[] = $this->urlEntry($this->generateUrl('public_games_anagrama', [], UrlGeneratorInterface::ABSOLUTE_URL), $today, 'daily', '0.5');
        $urls[] = $this->urlEntry($this->generateUrl('public_horoscope', [], UrlGeneratorInterface::ABSOLUTE_URL), $today, 'daily', '0.5');
        foreach (HoroscopeSignRegistry::all() as $sign) {
            $urls[] = $this->urlEntry(
                $this->generateUrl('public_horoscope_sign', ['slug' => $sign['slug']], UrlGeneratorInterface::ABSOLUTE_URL),
                $today,
                'daily',
                '0.45',
            );
        }
        $urls[] = $this->urlEntry($this->generateUrl('public_classifieds', [], UrlGeneratorInterface::ABSOLUTE_URL), $today, 'daily', '0.55');
        $urls[] = $this->urlEntry($this->generateUrl('public_forum', [], UrlGeneratorInterface::ABSOLUTE_URL), $today, 'daily', '0.55');
        $urls[] = $this->urlEntry($this->generateUrl('public_newsletter_index', [], UrlGeneratorInterface::ABSOLUTE_URL), null, 'monthly', '0.4');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as $entry) {
            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.htmlspecialchars($entry['loc'], ENT_XML1).'</loc>'."\n";
            if ($entry['lastmod'] !== null) {
                $xml .= '    <lastmod>'.$entry['lastmod'].'</lastmod>'."\n";
            }
            if ($entry['changefreq'] !== null) {
                $xml .= '    <changefreq>'.$entry['changefreq'].'</changefreq>'."\n";
            }
            if ($entry['priority'] !== null) {
                $xml .= '    <priority>'.$entry['priority'].'</priority>'."\n";
            }
            $xml .= '  </url>'."\n";
        }
        $xml .= '</urlset>';

        return $this->seoXmlResponse($xml);
    }

    #[Route('/sitemap-news.xml', name: 'public_sitemap_news', methods: ['GET'])]
    public function sitemapNews(
        SiteContextHolder $holder,
        SiteEntityManagerFactory $siteEmFactory,
        SiteSettingsReader $settingsReader,
    ): Response {
        $site = $holder->get();
        if ($site === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $em = $siteEmFactory->forContext($site);
        $articleRepo = $em->getRepository(Article::class);
        if (!$articleRepo instanceof ArticleRepository) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $seo = $settingsReader->getSeoConfig($site);
        $publicationName = $seo->resolvedSiteName($site->name);
        // Google News: solo piezas de las últimas ~48 h.
        $since = new \DateTimeImmutable('-2 days');
        $articles = $articleRepo->findPublishedSince($since, [], 1000);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">'."\n";
        foreach ($articles as $article) {
            $publishedAt = $article->getPublishedAt() ?? $article->getUpdatedAt();
            if ($publishedAt === null) {
                continue;
            }
            $loc = $this->publicUrls->normalize(
                $this->generateUrl('public_article_show', ['slug' => $article->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            );
            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'."\n";
            $xml .= '    <news:news>'."\n";
            $xml .= '      <news:publication>'."\n";
            $xml .= '        <news:name>'.htmlspecialchars($publicationName, ENT_XML1).'</news:name>'."\n";
            $xml .= '        <news:language>es</news:language>'."\n";
            $xml .= '      </news:publication>'."\n";
            $xml .= '      <news:publication_date>'.$publishedAt->format(\DateTimeInterface::ATOM).'</news:publication_date>'."\n";
            $xml .= '      <news:title>'.htmlspecialchars($article->getTitle(), ENT_XML1).'</news:title>'."\n";
            $xml .= '    </news:news>'."\n";
            $xml .= '  </url>'."\n";
        }
        $xml .= '</urlset>';

        return $this->seoXmlResponse($xml);
    }

    #[Route('/rss.xml', name: 'public_rss', methods: ['GET'])]
    public function rss(
        SiteContextHolder $holder,
        SiteEntityManagerFactory $siteEmFactory,
    ): Response {
        $site = $holder->get();
        if ($site === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $em = $siteEmFactory->forContext($site);
        $articleRepo = $em->getRepository(Article::class);
        if (!$articleRepo instanceof ArticleRepository) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return $this->renderRss(
            $site->name,
            $this->generateUrl('home', [], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->generateUrl('public_rss', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'Últimas noticias de '.$site->name,
            $articleRepo->findPublishedLatest(50),
        );
    }

    #[Route('/feed', name: 'public_feed', methods: ['GET'])]
    public function feedAlias(): Response
    {
        return $this->redirectToRoute('public_rss', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        '/categoria/{slug}/rss.xml',
        name: 'public_category_rss',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['GET'],
    )]
    public function categoryRss(
        string $slug,
        SiteContextHolder $holder,
        SiteEntityManagerFactory $siteEmFactory,
    ): Response {
        $site = $holder->get();
        if ($site === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $em = $siteEmFactory->forContext($site);
        $categoryRepo = $em->getRepository(Category::class);
        $articleRepo = $em->getRepository(Article::class);
        if (!$categoryRepo instanceof CategoryRepository || !$articleRepo instanceof ArticleRepository) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $category = $categoryRepo->findOneBySlugWithRelations($slug);
        if ($category === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $categoryIds = [$category->getId()];
        foreach ($category->getChildren() as $child) {
            if ($child->getId() !== null) {
                $categoryIds[] = $child->getId();
            }
        }

        return $this->renderRss(
            $category->getName().' — '.$site->name,
            $this->generateUrl('public_category_show', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->generateUrl('public_category_rss', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            $category->getDescription() !== null && $category->getDescription() !== ''
                ? $category->getDescription()
                : 'Noticias de '.$category->getName().' en '.$site->name,
            $articleRepo->findPublishedByCategoryIds(array_values(array_filter($categoryIds)), 50),
        );
    }

    /**
     * @param list<Article> $articles
     */
    private function renderRss(
        string $channelTitle,
        string $channelLink,
        string $feedUrl,
        string $channelDescription,
        array $articles,
    ): Response {
        $siteBase = rtrim($this->generateUrl('home', [], UrlGeneratorInterface::ABSOLUTE_URL), '/');
        $items = [];
        $lastBuild = null;

        foreach ($articles as $article) {
            $publishedAt = $article->getPublishedAt() ?? $article->getUpdatedAt();
            if ($lastBuild === null || $publishedAt > $lastBuild) {
                $lastBuild = $publishedAt;
            }

            $description = trim((string) ($article->getLead() ?: $article->getSubtitle() ?: ''));
            if ($description === '' && $article->getBodyHtml() !== '') {
                $description = trim(preg_replace('/\s+/', ' ', strip_tags($article->getBodyHtml())) ?? '');
            }
            if (mb_strlen($description) > 500) {
                $description = rtrim(mb_substr($description, 0, 497)).'…';
            }
            if ($description === '') {
                $description = $article->getTitle();
            }
            $description = str_replace(']]>', ']] >', $description);

            $items[] = [
                'title' => $article->getTitle(),
                'link' => $this->generateUrl('public_article_show', ['slug' => $article->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'pubDate' => $publishedAt->format(\DateTimeInterface::RSS),
                'author' => $article->getAuthorName(),
                'category' => $article->getCategory()?->getName(),
                'description' => $description,
                'image' => $this->absolutePublicUrl($article->getFeaturedImage()?->getUrl(), $siteBase),
                'imageType' => 'image/jpeg',
            ];
        }

        $response = $this->render('public/rss/feed.xml.twig', [
            'channelTitle' => $channelTitle,
            'channelLink' => $channelLink,
            'channelDescription' => $channelDescription,
            'feedUrl' => $feedUrl,
            'lastBuildDate' => ($lastBuild ?? new \DateTimeImmutable('now'))->format(\DateTimeInterface::RSS),
            'items' => $items,
        ]);
        $response->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');

        return $response;
    }

    private function absolutePublicUrl(?string $path, string $siteBase): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, '//')) {
            return 'https:'.$path;
        }

        return $siteBase.(str_starts_with($path, '/') ? $path : '/'.$path);
    }

    /** @return array{loc: string, lastmod: ?string, changefreq: ?string, priority: ?string} */
    private function urlEntry(string $loc, ?string $lastmod, ?string $changefreq, ?string $priority): array
    {
        return [
            'loc' => $this->publicUrls->normalize($loc),
            'lastmod' => $lastmod,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
    }

    private function seoTextResponse(string $body): Response
    {
        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private function seoXmlResponse(string $xml): Response
    {
        return new Response($xml, Response::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
