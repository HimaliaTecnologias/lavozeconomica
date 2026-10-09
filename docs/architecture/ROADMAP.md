# Roadmap de implementación

## Fase 0 — Fundamentos ✅ (actual)
- Docker Compose completo
- Symfony + dual Entity Manager
- BD plataforma + BD por sitio
- **Asistente de instalación `/install` (momento cero)**
- `site:create`, `migrate:site`, `migrate:sites`
- Health checks, API artículos, ingest HimtecMedia (básico)

## Fase 1 — MVP editorial
- CRUD artículos, categorías, autores, workflow
- Media upload
- Tema base Twig
- RSS, URLs amigables

## Fase 2 — Multi-periódico operativo
- `site_settings`, menús, páginas
- Portada por bloques JSON
- RBAC por sitio
- Caché Redis con tags

## Fase 3 — HimtecMedia completo
- JSON Schema validation
- Cola async ingest
- `site_publications` → artículos en BD sitio
- Panel errores / reproceso

## Fase 4 — Motor de reglas
- Reglas geo/temática/fuente/confianza
- Mapeo categorías
- UI admin reglas

## Fase 5 — SEO, rendimiento, publicidad
- Sitemaps, schema.org, redirects
- Imágenes WebP async
- Ad slots

## Fase 6 — Hardening
- Auditoría, backups, monitoring, CI/CD completo

## Fase 7+ — Módulos opcionales
Newsletters, push, paywall, comentarios, IA, etc.
