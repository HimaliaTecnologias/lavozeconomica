<?php

namespace App\Core\Site;

use App\Entity\Platform\Enum\SiteStatus;
use App\Entity\Platform\PlatformState;
use App\Entity\Platform\Site;
use Doctrine\ORM\EntityManagerInterface;

final class SiteContextResolver
{
    public function __construct(
        private readonly EntityManagerInterface $platformEntityManager,
        private readonly string $defaultSiteCode,
        private readonly string $publicBaseUrl = '',
    ) {}

    public function resolveByDomain(string $host): ?SiteContext
    {
        $site = null;
        foreach ($this->hostsToMatch($host) as $candidate) {
            $site = $this->findSiteByDomain($candidate);
            if ($site !== null) {
                break;
            }
        }

        if ($site === null && $this->defaultSiteCode !== '') {
            $site = $this->findSiteByCode($this->defaultSiteCode);
        }

        if ($site === null) {
            $primaryCode = $this->platformEntityManager->find(PlatformState::class, 1)?->getPrimarySiteCode();
            if (\is_string($primaryCode) && $primaryCode !== '') {
                $site = $this->findSiteByCode($primaryCode);
            }
        }

        if ($site === null) {
            $site = $this->platformEntityManager->getRepository(Site::class)
                ->findOneBy(['status' => SiteStatus::Active], ['id' => 'ASC']);
        }

        if ($site === null) {
            $site = $this->platformEntityManager->getRepository(Site::class)
                ->findOneBy([], ['id' => 'ASC']);
        }

        if ($site === null) {
            return null;
        }

        return $this->toContext($site, $this->normalizeHost($host));
    }

    public function resolveByCode(string $code): ?SiteContext
    {
        $site = $this->findSiteByCode($code);
        if ($site === null) {
            return null;
        }

        $domain = $site->getDomains()->first();
        $domainName = $domain ? $domain->getDomain() : $code;

        return $this->toContext($site, $domainName);
    }

    private function findSiteByCode(string $code): ?Site
    {
        return $this->platformEntityManager->getRepository(Site::class)
            ->findOneBy(['code' => $code]);
    }

    private function findSiteByDomain(string $host): ?Site
    {
        if ($host === '') {
            return null;
        }

        return $this->platformEntityManager->createQueryBuilder()
            ->select('s', 'd')
            ->from(Site::class, 's')
            ->join('s.domains', 'd')
            ->where('d.domain = :domain')
            ->setParameter('domain', $host)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function toContext(Site $site, string $fallbackHost): ?SiteContext
    {
        $id = $site->getId();
        if ($id === null) {
            return null;
        }

        return new SiteContext(
            id: $id,
            code: $site->getCode(),
            name: $site->getName(),
            domain: $this->resolvePrimaryDomain($site, $fallbackHost),
        );
    }

    /** @return list<string> */
    private function hostsToMatch(string $host): array
    {
        $hosts = $this->hostCandidates($this->normalizeHost($host));
        $publicHost = $this->hostFromUrl($this->publicBaseUrl);
        if ($publicHost !== '') {
            $hosts = array_merge($hosts, $this->hostCandidates($publicHost));
        }

        return array_values(array_unique(array_filter($hosts, static fn (string $h): bool => $h !== '')));
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? $host);

        return rtrim($host, '.');
    }

    /** @return list<string> */
    private function hostCandidates(string $host): array
    {
        $host = $this->normalizeHost($host);
        if ($host === '') {
            return [];
        }

        $candidates = [$host];
        if (str_starts_with($host, 'www.')) {
            $apex = substr($host, 4);
            if ($apex !== '') {
                $candidates[] = $apex;
            }
        } elseif ($host !== 'localhost' && filter_var($host, FILTER_VALIDATE_IP) === false) {
            $candidates[] = 'www.'.$host;
        }

        return $candidates;
    }

    private function hostFromUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return '';
        }
        $host = parse_url($url, PHP_URL_HOST);

        return \is_string($host) ? $this->normalizeHost($host) : '';
    }

    private function resolvePrimaryDomain(Site $site, string $fallback): string
    {
        foreach ($site->getDomains() as $domain) {
            if ($domain->isPrimary()) {
                return $domain->getDomain();
            }
        }

        $first = $site->getDomains()->first();
        if ($first) {
            return $first->getDomain();
        }

        return $fallback;
    }
}
