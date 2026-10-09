# MyMediaCMS

CMS multi-periódico para medios digitales locales, comarcales y temáticos.

**Arquitectura:** código compartido + **BD Plataforma** (registro, ingesta HimtecMedia) + **BD dedicada por periódico** (contenido editorial).

## Requisitos

- Docker y Docker Compose
- Git

## Inicio rápido

```bash
./bin/setup.sh
```

Abre el **asistente de instalación (momento cero)**:

**http://localhost:19140/install**

El asistente guía la configuración del primer periódico: nombre, dominio, colores, cuenta de administrador y creación automática de su base de datos.

## Instalación manual

```bash
docker compose up -d --build
docker compose run --rm php composer install --no-interaction --no-scripts
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

Luego visita `/install`.

## URLs locales

| URL | Descripción |
|-----|-------------|
| http://localhost:19140/install | Asistente de instalación (momento cero) |
| http://localhost:19140 | Portada del periódico |
| http://localhost:19140/api/v1/articles | API artículos |
| http://localhost:19140/api/v1/ingest/himtecmedia | Ingesta HimtecMedia |
| http://localhost:19140/health/ready | Health check |

## Comandos útiles

```bash
# Periódico adicional (tras la instalación inicial)
php bin/console site:create --code=asturias --name="Diario Asturias" --domain=asturias.local

# Migrar todos los sitios
php bin/console migrate:sites

# Migrar un sitio
php bin/console migrate:site --code=demo
```

## Estructura

```
src/Install/         # Asistente de instalación (momento cero)
src/Core/Site/       # Resolución multi-periódico
src/Entity/Platform/ # BD plataforma
src/Entity/Site/     # BD periódico
src/Ingestion/       # HimtecMedia (desacoplado)
migrations/platform/ # Migraciones plataforma
docker/              # Nginx, PHP, MySQL init
docs/architecture/   # Documentación
```

## Documentación

- [Arquitectura](docs/architecture/ARCHITECTURE.md)
- [Instalación / Momento cero](docs/architecture/INSTALL.md)
- [Roadmap](docs/architecture/ROADMAP.md)

## Stack

PHP 8.3 · Symfony 7.4 · MariaDB 11.4 · Redis 7 · Twig · Docker
