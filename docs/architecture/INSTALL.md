# Asistente de instalación (Momento cero)

Tras desplegar la plataforma por primera vez, cualquier visita redirige automáticamente a `/install` hasta completar la configuración inicial.

## Pasos del asistente

1. **Bienvenida** — Introducción al proceso
2. **Periódico** — Nombre, código interno, dominio, idioma, zona horaria
3. **Apariencia** — Colores corporativos y eslogan
4. **Administrador** — Cuenta superadmin + admin del periódico
5. **Resumen e instalación** — Crea BD del sitio, settings y marca plataforma como instalada

## Qué ocurre al finalizar

- Registro del sitio en BD **plataforma** (`sites`, `site_domains`, `site_database_config`)
- Creación de BD dedicada `mymedia_site_{codigo}`
- Schema editorial en BD del sitio (`articles`, `site_settings`, …)
- Configuración inicial en `site_settings` (tema, identidad, SEO)
- Usuario administrador en BD plataforma con rol `site_admin`
- `platform_state.installed_at` queda marcado

## Probar de nuevo (desarrollo)

```bash
docker compose down -v
./bin/setup.sh
# Abrir http://localhost:19140/install
```

## Añadir más periódicos después

El asistente solo se ejecuta en el **momento cero**. Periódicos adicionales:

```bash
php bin/console site:create --code=otro --name="Otro Medio" --domain=otro.local
```

(La configuración visual de sitios adicionales se gestionará desde el panel admin en fases posteriores.)
