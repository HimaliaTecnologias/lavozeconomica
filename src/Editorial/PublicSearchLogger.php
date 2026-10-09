<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\SearchQueryLog;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

final class PublicSearchLogger
{
    private const SESSION_KEY = 'archive_search_logged';

    public function __construct(
        private readonly SiteEntityManagerFactory $siteEmFactory,
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
    ) {}

    public function markPending(Request $request): void
    {
        $request->getSession()->remove(self::SESSION_KEY);
    }

    public function recordIfNeeded(
        SiteContext $site,
        Request $request,
        string $query,
        int $page,
        int $resultsCount,
    ): void {
        if ($query === '' || $page !== 1) {
            return;
        }

        $session = $request->getSession();
        if ($session->get(self::SESSION_KEY) === $query) {
            return;
        }

        try {
            $em = $this->siteEmFactory->forContext($site);
            $em->persist(new SearchQueryLog($query, $resultsCount, $this->visitorHash($request)));
            $em->flush();
            $session->set(self::SESSION_KEY, $query);
        } catch (\Throwable) {
            // La búsqueda pública no debe fallar si aún no existe la tabla de log.
        }
    }

    private function visitorHash(Request $request): ?string
    {
        $ip = $request->getClientIp() ?? '';
        if ($ip === '') {
            return null;
        }

        return substr(hash('sha256', $ip.'|'.$this->appSecret), 0, 16);
    }
}
