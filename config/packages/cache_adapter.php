<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * El adapter de cache.app debe ser un service id estático (no %env()%).
 * Se elige al compilar el contenedor según CACHE_APP_ADAPTER.
 *
 * - cache.adapter.redis_tag_aware (default, requiere REDIS_URL)
 * - cache.adapter.filesystem_tag_aware (sin Redis)
 */
return static function (ContainerConfigurator $container): void {
    $adapter = $_SERVER['CACHE_APP_ADAPTER'] ?? $_ENV['CACHE_APP_ADAPTER'] ?? 'cache.adapter.redis_tag_aware';
    $allowed = [
        'cache.adapter.redis_tag_aware',
        'cache.adapter.filesystem_tag_aware',
        'cache.adapter.array',
        'cache.adapter.filesystem',
    ];
    if (!\in_array($adapter, $allowed, true)) {
        $adapter = 'cache.adapter.redis_tag_aware';
    }

    $container->extension('framework', [
        'cache' => [
            'app' => $adapter,
        ],
    ]);
};
