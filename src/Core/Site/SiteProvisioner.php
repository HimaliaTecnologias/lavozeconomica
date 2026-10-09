<?php

namespace App\Core\Site;

use App\Entity\Platform\Site;
use App\Entity\Platform\SiteDatabaseConfig;
use App\Entity\Platform\SiteDomain;
use App\Entity\Site\AdBanner;
use App\Editorial\CommunityCategorySeeder;
use App\Repository\Site\AdBannerRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SiteProvisioner
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
        #[Autowire(service: 'doctrine.dbal.platform_connection')]
        private readonly Connection $platformConnection,
        private readonly SiteContextResolver $resolver,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly CommunityCategorySeeder $communityCategorySeeder,
        #[Autowire('%env(PROVISION_DB_HOST)%')]
        private readonly string $dbHost,
        #[Autowire('%env(PROVISION_DB_USER)%')]
        private readonly string $dbUser,
        #[Autowire('%env(PROVISION_DB_PASSWORD)%')]
        private readonly string $dbPassword,
        #[Autowire('%env(PROVISION_DB_ROOT_USER)%')]
        private readonly string $dbRootUser,
        #[Autowire('%env(PROVISION_DB_ROOT_PASSWORD)%')]
        private readonly string $dbRootPassword,
    ) {}

    public function provision(
        string $code,
        string $name,
        string $domain,
        string $locale,
        string $timezone,
    ): Site {
        $code = strtolower(trim($code));
        $domain = strtolower(trim($domain));

        if ($this->platformEm->getRepository(Site::class)->findOneBy(['code' => $code])) {
            throw new \InvalidArgumentException("El código '{$code}' ya está en uso.");
        }

        $databaseName = 'mymedia_site_'.$code;
        $this->createSiteDatabase($databaseName);

        $site = new Site($code, $name);
        $site->setDefaultLocale($locale);
        $site->setTimezone($timezone);
        $site->addDomain(new SiteDomain($site, $domain, true));
        $site->setDatabaseConfig(new SiteDatabaseConfig(
            $site,
            $this->dbHost,
            $databaseName,
            $this->dbUser,
            $this->dbPassword,
        ));

        $this->platformEm->persist($site);
        $this->platformEm->flush();

        $this->migrateSiteSchema($code);

        return $site;
    }

    public function migrateSiteSchema(string $code): void
    {
        $context = $this->resolver->resolveByCode($code);
        if ($context === null) {
            throw new \RuntimeException("No se pudo resolver el sitio '{$code}' tras el registro.");
        }

        $site = $this->platformEm->getRepository(Site::class)->findOneBy(['code' => $code]);
        $databaseName = $site?->getDatabaseConfig()?->getDatabaseName() ?? 'mymedia_site_'.$code;
        $this->repairSiteDatabaseConfig($site);
        $this->createSiteDatabase($databaseName);

        $em = $this->siteEmFactory->forContext($context);
        $this->communityCategorySeeder->prepareLegacyMigration($em->getConnection());
        $schemaTool = new SchemaTool($em);
        $schemaTool->updateSchema($em->getMetadataFactory()->getAllMetadata());
        $this->communityCategorySeeder->ensureDefaults($em);

        $adRepo = $em->getRepository(AdBanner::class);
        if ($adRepo instanceof AdBannerRepository) {
            $adRepo->backfillPlacementsJson();
        }
    }

    private function repairSiteDatabaseConfig(?Site $site): void
    {
        $config = $site?->getDatabaseConfig();
        if ($config === null) {
            return;
        }

        $changed = false;
        $host = strtolower(trim($config->getHost()));
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || $host === '0.0.0.0') {
            $config->setHost($this->dbHost);
            $changed = true;
        }

        // Dentro de Docker el puerto interno es siempre 3306.
        if ($config->getPort() !== 3306 && ($config->getHost() === 'mysql' || $config->getHost() === $this->dbHost)) {
            $config->setPort(3306);
            $changed = true;
        }

        if ($config->getPasswordEncrypted() === '' && $this->dbPassword !== '') {
            $config->setPasswordEncrypted($this->dbPassword);
            $changed = true;
        }

        if ($changed) {
            $this->platformEm->flush();
        }
    }

    private function createSiteDatabase(string $databaseName): void
    {
        $root = $this->createRootConnection();

        $root->executeStatement(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $databaseName,
        ));

        $root->executeStatement(sprintf(
            "GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%%'",
            $databaseName,
            addslashes($this->dbUser),
        ));
        $root->executeStatement('FLUSH PRIVILEGES');
    }

    private function createRootConnection(): Connection
    {
        // Params limpios: clonar getParams() del platform arrastra unix_socket/wrapperClass
        // y PDO acaba en "Connection refused" dentro de Docker aunque mysql:3306 responda.
        $base = $this->platformConnection->getParams();
        $host = trim((string) ($base['host'] ?? ''));
        if ($host === '' || \in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            $host = $this->dbHost !== '' ? $this->dbHost : 'mysql';
        }

        $params = [
            'driver' => $base['driver'] ?? 'pdo_mysql',
            'host' => $host,
            'port' => 3306,
            'user' => $this->dbRootUser,
            'password' => $this->dbRootPassword,
            'charset' => $base['charset'] ?? 'utf8mb4',
        ];
        if (isset($base['serverVersion'])) {
            $params['serverVersion'] = $base['serverVersion'];
        }

        return DriverManager::getConnection(
            $params,
            $this->platformConnection->getConfiguration(),
        );
    }
}

