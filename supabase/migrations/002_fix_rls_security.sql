-- ============================================================================
-- Migracion: Correcciones de seguridad en RLS
-- ============================================================================
-- Problemas corregidos:
--   1. current_tenant_id() usaba COALESCE(..., 1) — peticiones sin autenticar
--      veian los datos del tenant 1. Ahora devuelve NULL para bloquearlas.
--   2. Tablas de log tenian INSERT WITH CHECK (true) — cualquier usuario
--      autenticado podia insertar en cualquier tenant. Corregido a tenant propio.
-- ============================================================================

BEGIN;

-- ─── Fix 1: current_tenant_id() sin fallback inseguro ───────────────────────
-- NULL bloqueara automaticamente el acceso via RLS (NULL = X es NULL, no TRUE)
CREATE OR REPLACE FUNCTION current_tenant_id()
RETURNS integer
LANGUAGE sql STABLE
AS $$
  SELECT (auth.jwt() -> 'app_metadata' ->> 'tenant_id')::integer;
$$;

-- ─── Fix 2: INSERT policies tenant-restringidas en tablas de log ─────────────

-- eventos_timeline
DROP POLICY IF EXISTS tenant_insert ON public.eventos_timeline;
CREATE POLICY tenant_insert ON public.eventos_timeline
  FOR INSERT WITH CHECK (tenant_id = current_tenant_id());

-- historial_mensajes
DROP POLICY IF EXISTS tenant_insert ON public.historial_mensajes;
CREATE POLICY tenant_insert ON public.historial_mensajes
  FOR INSERT WITH CHECK (tenant_id = current_tenant_id());

-- sesiones_log
DROP POLICY IF EXISTS tenant_insert ON public.sesiones_log;
CREATE POLICY tenant_insert ON public.sesiones_log
  FOR INSERT WITH CHECK (tenant_id = current_tenant_id());

-- actividad_logs
DROP POLICY IF EXISTS tenant_insert ON public.actividad_logs;
CREATE POLICY tenant_insert ON public.actividad_logs
  FOR INSERT WITH CHECK (tenant_id = current_tenant_id());

COMMIT;
