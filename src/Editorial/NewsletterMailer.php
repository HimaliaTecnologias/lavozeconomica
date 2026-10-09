<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use App\Entity\Site\Article;
use App\Entity\Site\NewsletterSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class NewsletterMailer
{
    public function __construct(
        private readonly SiteMailer $siteMailer,
        private readonly SiteSettingsReader $settingsReader,
        private readonly RouterInterface $router,
        #[Autowire(env: 'string:default::NEWSLETTER_FROM')]
        private readonly string $newsletterFrom = '',
    ) {}

    public function sendConfirmation(
        SiteContext $site,
        NewsletterSubscriber $subscriber,
        EntityManagerInterface $em,
    ): void {
        $this->configureRouterContext($site);

        $confirmUrl = $this->router->generate(
            'public_newsletter_confirm',
            ['token' => $subscriber->getConfirmToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $theme = $this->settingsReader->getTheme($site);

        $email = (new TemplatedEmail())
            ->from($this->fromAddress($site))
            ->to($subscriber->getEmail())
            ->subject('Confirma tu suscripción al boletín — '.$site->name)
            ->htmlTemplate('email/newsletter/confirm.html.twig')
            ->context([
                'site' => $site,
                'theme' => $theme,
                'subscriber' => $subscriber,
                'confirmUrl' => $confirmUrl,
            ]);

        $this->siteMailer->send($site, $email);
    }

    /**
     * @param list<Article> $articles
     */
    public function sendDigest(
        SiteContext $site,
        NewsletterSubscriber $subscriber,
        array $articles,
        string $periodLabel,
        EntityManagerInterface $em,
    ): void {
        if ($articles === []) {
            return;
        }

        $this->configureRouterContext($site);

        $unsubscribeUrl = $this->router->generate(
            'public_newsletter_unsubscribe',
            ['token' => $subscriber->getUnsubscribeToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $preferencesUrl = $this->router->generate(
            'public_newsletter_preferences',
            ['token' => $subscriber->getUnsubscribeToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $homeUrl = $this->router->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $articleRows = [];
        foreach ($articles as $article) {
            $articleRows[] = [
                'title' => $article->getTitle(),
                'lead' => $article->getLead() ?? $article->getSubtitle(),
                'url' => $this->router->generate(
                    'public_article_show',
                    ['slug' => $article->getSlug()],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
                'category' => $article->getCategory()?->getName(),
                'publishedAt' => $article->getPublishedAt(),
            ];
        }

        $theme = $this->settingsReader->getTheme($site);

        $email = (new TemplatedEmail())
            ->from($this->fromAddress($site))
            ->to($subscriber->getEmail())
            ->subject($periodLabel.' — '.$site->name)
            ->htmlTemplate('email/newsletter/digest.html.twig')
            ->context([
                'site' => $site,
                'theme' => $theme,
                'subscriber' => $subscriber,
                'periodLabel' => $periodLabel,
                'articles' => $articleRows,
                'homeUrl' => $homeUrl,
                'unsubscribeUrl' => $unsubscribeUrl,
                'preferencesUrl' => $preferencesUrl,
            ]);

        $this->siteMailer->send($site, $email);
    }

    private function fromAddress(SiteContext $site): string
    {
        if ($this->newsletterFrom !== '') {
            return $this->newsletterFrom;
        }

        $footer = $this->settingsReader->getFooterConfig($site);
        if ($footer->contactEmail !== '') {
            return $footer->contactEmail;
        }

        return 'noreply@'.$site->domain;
    }

    private function configureRouterContext(SiteContext $site): void
    {
        $context = $this->router->getContext();
        $context->setHost($site->domain);
        $context->setScheme(str_contains($site->domain, 'localhost') ? 'http' : 'https');
    }
}
