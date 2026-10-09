<?php

namespace App\Core\Site;

use App\Entity\Platform\Site;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SiteConnectionManager
{
    /** @var array<string, Connection> */
    private array $connections = [];

    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEntityManager,
        #[Autowire(service: 'doctrine.dbal.site_connection')]
        private readonly Connection $templateConnection,
    ) {}

    public function getConnectionForSite(SiteContext $context): Connection
    {
        if (isset($this->connections[$context->code])) {
            return $this->connections[$context->code];
        }

        $site = $this->platformEntityManager->find(Site::class, $context->id);
        if ($site === null || $site->getDatabaseConfig() === null) {
            $this->connections[$context->code] = $this->templateConnection;

            return $this->templateConnection;
        }

        $config = $site->getDatabaseConfig();
        $base = $this->templateConnection->getParams();
        // Si dejamos "url", DriverManager la reparsea y pisa host/puerto/dbname del sitio
        // (p. ej. SITE_DATABASE_URL con 127.0.0.1:3309 → Connection refused dentro de Docker).
        unset($base['url'], $base['primary'], $base['replica']);

        $host = $config->getHost();
        $port = $config->getPort();
        $templateHost = (string) ($base['host'] ?? 'mysql');

        if ($this->isLoopbackHost($host)) {
            $host = $this->isLoopbackHost($templateHost) ? 'mysql' : $templateHost;
            $port = 3306;
        }

        // Servicio Docker "mysql": siempre el puerto interno, nunca el publicado del host (3309…).
        if ($this->isDockerMysqlHost($host) || $port <= 0 || $this->looksLikePublishedHostPort($port, $host)) {
            $port = 3306;
        }

        // Solo claves PDO seguras: no reutilizar unix_socket / wrapperClass / url del template.
        $params = [
            'driver' => $base['driver'] ?? 'pdo_mysql',
            'host' => $host,
            'port' => $port,
            'dbname' => $config->getDatabaseName(),
            'user' => $config->getUsername(),
            'password' => $config->getPasswordEncrypted(),
            'charset' => $base['charset'] ?? 'utf8mb4',
        ];
        if (isset($base['serverVersion'])) {
            $params['serverVersion'] = $base['serverVersion'];
        }

        $connection = DriverManager::getConnection(
            $params,
            $this->templateConnection->getConfiguration(),
        );

        $this->connections[$context->code] = $connection;

        return $connection;
    }

    private function isLoopbackHost(string $host): bool
    {
        $host = strtolower(trim($host));

        return $host === ''
            || $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || $host === '0.0.0.0';
    }

    private function isDockerMysqlHost(string $host): bool
    {
        $host = strtolower(trim($host));

        return $host === 'mysql'
            || str_ends_with($host, '-mysql-1')
            || str_contains($host, '_mysql');
    }

    /** Puertos típicos publicados en el host (Compose), inválidos dentro de la red Docker. */
    private function looksLikePublishedHostPort(int $port, string $host): bool
    {
        if (!$this->isDockerMysqlHost($host) && !$this->isLoopbackHost($host)) {
            return false;
        }

        return $port !== 3306 && $port > 3306 && $port < 4000;
    }
}
