# SolCel App

Sistema de gestión para taller de reparación de celulares sobre Supabase (PostgreSQL). Panel de control para reparaciones, inventario, clientes, soporte y analíticas con IA.

## Requisitos

- **PHP** >= 8.0
- **PostgreSQL** (Supabase self-hosted vía Dokploy)
- Extensiones PHP: `curl`, `json`, `mbstring`

## Estructura del proyecto

```
solcel_app/
├── api/              # Endpoints API (JSON)
├── assets/           # CSS, JS, imágenes
├── config/           # auth, db, env_loader
├── cron/             # Scripts programados (Mes Azul)
├── database/         # Migraciones SQL y schema_map
├── includes/         # head_meta, header, pwa_script
├── modules/          # Vistas de módulos (analíticas, clientes, inventario, etc.)
├── src/              # Lógica de negocio (repositorios y servicios)
│   ├── Analiticas/
│   ├── Clientes/
│   ├── Inventario/
│   ├── Reparaciones/
│   ├── Shared/
│   ├── Soporte/
│   └── Usuarios/
├── index.php         # Panel principal de reparaciones
├── login.php         # Página de login
└── .env.example      # Plantilla de variables de entorno
```

## Instalación

1. Clonar o copiar el proyecto.
2. Copiar `.env.example` a `.env` y configurar:
   - `SUPABASE_URL`, `SUPABASE_ANON_KEY`, `SUPABASE_SERVICE_ROLE_KEY` — API Supabase (Auth/REST/Storage)
   - `SUPABASE_DB_HOST`, `SUPABASE_DB_PORT`, `SUPABASE_DB_NAME`, `SUPABASE_DB_USER`, `SUPABASE_DB_PASS` — Conexión directa PostgreSQL (para migraciones)
   - `DEEPSEEK_API_KEY` — Para asistente IA (opcional)
   - `N8N_WEBHOOK_*` — Webhooks para notificaciones WhatsApp (opcional)
3. Ejecutar `composer install` para generar el autoload.
4. Ejecutar migraciones SQL desde `database/migrations/` en orden numérico (0001 → 0006).
5. Ejecutar `setup_primera_vez.php` UNA SOLA VEZ para crear el tenant y admin inicial, luego **eliminar** ese archivo.
6. Configurar el servidor web (Apache/Nginx) con el documento raíz en la carpeta del proyecto.

## Healthcheck Supabase

- Endpoint backend: `api/api_supabase_health.php`
- Requiere sesión iniciada y rol admin.
- Valida conectividad y credenciales contra:
  - `GET /auth/v1/settings`
  - `GET /rest/v1/`
  - `GET /storage/v1/bucket`

Si en local Windows aparece `unable to get local issuer certificate`, descarga [cacert.pem](https://curl.se/ca/cacert.pem) y en `.env` define `SUPABASE_CA_BUNDLE=C:\ruta\cacert.pem`, o configura `curl.cainfo` en `php.ini`. Solo en desarrollo: `SUPABASE_VERIFY_SSL=false`.

## PWA

El proyecto incluye soporte PWA: `manifest.json`, `service-worker.js`, `offline.html`.

## Cron

Para el flujo Mes Azul (equipos listos sin recoger):

```bash
0 8 * * * cd /ruta/solcel_app && php cron/mes_azul_diario.php >> /var/log/mes_azul.log 2>&1
```
