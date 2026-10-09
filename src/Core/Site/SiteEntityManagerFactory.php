<?php

namespace App\Core\Site;

use App\Editorial\ArticleFeaturedImageGuard;
use App\Entity\Site\Article;
use App\Repository\Site\ArticleRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Proxy\ProxyFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SiteEntityManagerFactory
{
    /** @var array<string, EntityManagerInterface> */
    private array $managers = [];

    public function __construct(
        private readonly SiteConnectionManager $connectionManager,
        private readonly ArticleFeaturedImageGuard $featuredImageGuard,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
        #[Autowire('%kernel.cache_dir%')]
        private readonly string $cacheDir,
    ) {}

    public function forContext(SiteContext $context): EntityManagerInterface
    {
        if (isset($this->managers[$context->code])) {
            return $this->managers[$context->code];
        }

        $connection = $this->connectionManager->getConnectionForSite($context);

        // Evita sys_get_temp_dir()/tmp: si consola corre como root y FPM como www-data,
        // el rename de proxies en /tmp falla con "Operation not permitted".
        $proxyDir = $this->cacheDir.'/doctrine/orm/Proxies';
        $metaCacheDir = $this->cacheDir.'/doctrine/orm/site';
        foreach ([$proxyDir, $metaCacheDir] as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('No se pudo crear el directorio Doctrine: %s', $dir));
            }
            @chmod($dir, 0775);
        }

        // ORMSetup en prod (!debug) intenta Redis/Memcached en 127.0.0.1 si no pasas cache.
        // En Docker eso es "Connection refused" (Redis está en el servicio "redis").
        $cache = $this->debug
            ? new ArrayAdapter()
            : new FilesystemAdapter('site_orm_'.$context->code, 0, $metaCacheDir);

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [$this->projectDir.'/src/Entity/Site'],
            isDevMode: $this->debug,
            proxyDir: $proxyDir,
            cache: $cache,
        );
        $config->setNamingStrategy(new UnderscoreNamingStrategy());
        $config->setProxyNamespace('DoctrineProxies\\Site');
        // En prod ORMSetup pone NEVER → Class "DoctrineProxies\Site\..." not found + TypeError.
        // Generar el fichero la primera vez que falte (www-data debe poder escribir en proxyDir).
        $config->setAutoGenerateProxyClasses(
            $this->debug
                ? ProxyFactory::AUTOGENERATE_ALWAYS
                : ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS
        );

        $em = new EntityManager($connection, $config);
        $repo = $em->getRepository(Article::class);
        if ($repo instanceof ArticleRepository) {
            $repo->setFeaturedImageGuard($this->featuredImageGuard);
        }

        return $this->managers[$context->code] = $em;
    }
}
