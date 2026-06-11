-- ============================================================================
-- Migracion: RLS completo para TODAS las tablas del codebase
-- ============================================================================
-- Reemplaza: 0004_full_rls.sql (claim path incorrecto) + 001_enable_rls.sql (incompleta)
-- Claim path correcto: auth.jwt() -> 'app_metadata' ->> 'tenant_id'
-- (almacenado via adminCreateUser en UsuarioService.php)
-- ============================================================================

BEGIN;

-- ═══════════════════════════════════════════════════════
-- FUNCION AUXILIAR
-- ═══════════════════════════════════════════════════════

CREATE OR REPLACE FUNCTION current_tenant_id()
RETURNS integer
LANGUAGE sql STABLE
AS $$
  SELECT COALESCE(
    (auth.jwt() -> 'app_metadata' ->> 'tenant_id')::integer,
    1
  );
$$;

-- ═══════════════════════════════════════════════════════
-- TABLAS CON tenant_id (aislamiento por tenant)
-- Patron: SELECT/INSERT/UPDATE/DELETE para authenticated,
--         ALL para service_role
-- ═══════════════════════════════════════════════════════

-- Macro: crea politicas tenant para una tabla
-- Se aplica a: clientes, reparaciones, inventario, etc.

-- clientes
ALTER TABLE IF EXISTS public.clientes ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.clientes;
CREATE POLICY tenant_select ON public.clientes FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.clientes;
CREATE POLICY tenant_insert ON public.clientes FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.clientes;
CREATE POLICY tenant_update ON public.clientes FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.clientes;
CREATE POLICY tenant_delete ON public.clientes FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.clientes;
CREATE POLICY service_all ON public.clientes FOR ALL TO service_role USING (true) WITH CHECK (true);

-- reparaciones
ALTER TABLE IF EXISTS public.reparaciones ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.reparaciones;
CREATE POLICY tenant_select ON public.reparaciones FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.reparaciones;
CREATE POLICY tenant_insert ON public.reparaciones FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.reparaciones;
CREATE POLICY tenant_update ON public.reparaciones FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.reparaciones;
CREATE POLICY tenant_delete ON public.reparaciones FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.reparaciones;
CREATE POLICY service_all ON public.reparaciones FOR ALL TO service_role USING (true) WITH CHECK (true);

-- usuarios
ALTER TABLE IF EXISTS public.usuarios ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.usuarios;
CREATE POLICY tenant_select ON public.usuarios FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.usuarios;
CREATE POLICY tenant_insert ON public.usuarios FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.usuarios;
CREATE POLICY tenant_update ON public.usuarios FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.usuarios;
CREATE POLICY tenant_delete ON public.usuarios FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.usuarios;
CREATE POLICY service_all ON public.usuarios FOR ALL TO service_role USING (true) WITH CHECK (true);

-- inventario (ELIMINADA en 0008 — legacy, reemplazada por inv_accesorios, inv_baterias, inv_pantallas, inv_servicios_generales)
ALTER TABLE IF EXISTS public.inventario ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.inventario;
CREATE POLICY tenant_select ON public.inventario FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.inventario;
CREATE POLICY tenant_insert ON public.inventario FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.inventario;
CREATE POLICY tenant_update ON public.inventario FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.inventario;
CREATE POLICY tenant_delete ON public.inventario FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.inventario;
CREATE POLICY service_all ON public.inventario FOR ALL TO service_role USING (true) WITH CHECK (true);

-- inventario_categorias (ELIMINADA en 0008 — reemplazada por tablas inv_* individuales)
ALTER TABLE IF EXISTS public.inventario_categorias ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.inventario_categorias;
CREATE POLICY tenant_select ON public.inventario_categorias FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.inventario_categorias;
CREATE POLICY tenant_insert ON public.inventario_categorias FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.inventario_categorias;
CREATE POLICY tenant_update ON public.inventario_categorias FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.inventario_categorias;
CREATE POLICY tenant_delete ON public.inventario_categorias FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.inventario_categorias;
CREATE POLICY service_all ON public.inventario_categorias FOR ALL TO service_role USING (true) WITH CHECK (true);

-- ═══════════════════════════════════════════════════════
-- TABLAS ELIMINADAS EN PRODUCCION (migracion 0008)
-- Las politicas abajo son NO-OP (ALTER TABLE IF EXISTS no falla,
-- pero las tablas ya no existen). Se conservan como documentacion.
-- Tablas: inventario, equipos_marcas, accesorios_colores,
--         accesorios_marcas, accesorios_subcategorias,
--         pantallas_modelos, pantallas_modelos_tecnicos,
--         soporte, garantias, plantillas
-- ═══════════════════════════════════════════════════════

-- inventario (ELIMINADA en 0008)
ALTER TABLE IF EXISTS public.inventario ENABLE ROW LEVEL SECURITY;

-- reparacion_garantias
ALTER TABLE IF EXISTS public.reparacion_garantias ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.reparacion_garantias;
CREATE POLICY tenant_select ON public.reparacion_garantias FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.reparacion_garantias;
CREATE POLICY tenant_insert ON public.reparacion_garantias FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.reparacion_garantias;
CREATE POLICY tenant_update ON public.reparacion_garantias FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.reparacion_garantias;
CREATE POLICY tenant_delete ON public.reparacion_garantias FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.reparacion_garantias;
CREATE POLICY service_all ON public.reparacion_garantias FOR ALL TO service_role USING (true) WITH CHECK (true);

-- eventos_timeline
ALTER TABLE IF EXISTS public.eventos_timeline ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.eventos_timeline;
CREATE POLICY tenant_select ON public.eventos_timeline FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.eventos_timeline;
CREATE POLICY tenant_insert ON public.eventos_timeline FOR INSERT WITH CHECK (true);
DROP POLICY IF EXISTS service_all ON public.eventos_timeline;
CREATE POLICY service_all ON public.eventos_timeline FOR ALL TO service_role USING (true) WITH CHECK (true);

-- historial_mensajes
ALTER TABLE IF EXISTS public.historial_mensajes ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.historial_mensajes;
CREATE POLICY tenant_select ON public.historial_mensajes FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.historial_mensajes;
CREATE POLICY tenant_insert ON public.historial_mensajes FOR INSERT WITH CHECK (true);
DROP POLICY IF EXISTS tenant_update ON public.historial_mensajes;
CREATE POLICY tenant_update ON public.historial_mensajes FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.historial_mensajes;
CREATE POLICY service_all ON public.historial_mensajes FOR ALL TO service_role USING (true) WITH CHECK (true);

-- configuracion_mensajes
ALTER TABLE IF EXISTS public.configuracion_mensajes ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.configuracion_mensajes;
CREATE POLICY tenant_select ON public.configuracion_mensajes FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.configuracion_mensajes;
CREATE POLICY tenant_insert ON public.configuracion_mensajes FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.configuracion_mensajes;
CREATE POLICY tenant_update ON public.configuracion_mensajes FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.configuracion_mensajes;
CREATE POLICY tenant_delete ON public.configuracion_mensajes FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.configuracion_mensajes;
CREATE POLICY service_all ON public.configuracion_mensajes FOR ALL TO service_role USING (true) WITH CHECK (true);

-- sesiones_log
ALTER TABLE IF EXISTS public.sesiones_log ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.sesiones_log;
CREATE POLICY tenant_select ON public.sesiones_log FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.sesiones_log;
CREATE POLICY tenant_insert ON public.sesiones_log FOR INSERT WITH CHECK (true);
DROP POLICY IF EXISTS service_all ON public.sesiones_log;
CREATE POLICY service_all ON public.sesiones_log FOR ALL TO service_role USING (true) WITH CHECK (true);

-- actividad_logs
ALTER TABLE IF EXISTS public.actividad_logs ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.actividad_logs;
CREATE POLICY tenant_select ON public.actividad_logs FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.actividad_logs;
CREATE POLICY tenant_insert ON public.actividad_logs FOR INSERT WITH CHECK (true);
DROP POLICY IF EXISTS service_all ON public.actividad_logs;
CREATE POLICY service_all ON public.actividad_logs FOR ALL TO service_role USING (true) WITH CHECK (true);

-- notificaciones_sistema
ALTER TABLE IF EXISTS public.notificaciones_sistema ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.notificaciones_sistema;
CREATE POLICY tenant_select ON public.notificaciones_sistema FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.notificaciones_sistema;
CREATE POLICY tenant_insert ON public.notificaciones_sistema FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.notificaciones_sistema;
CREATE POLICY tenant_update ON public.notificaciones_sistema FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.notificaciones_sistema;
CREATE POLICY tenant_delete ON public.notificaciones_sistema FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.notificaciones_sistema;
CREATE POLICY service_all ON public.notificaciones_sistema FOR ALL TO service_role USING (true) WITH CHECK (true);

-- bot_conversaciones
ALTER TABLE IF EXISTS public.bot_conversaciones ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.bot_conversaciones;
CREATE POLICY tenant_select ON public.bot_conversaciones FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.bot_conversaciones;
CREATE POLICY tenant_insert ON public.bot_conversaciones FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.bot_conversaciones;
CREATE POLICY tenant_update ON public.bot_conversaciones FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.bot_conversaciones;
CREATE POLICY service_all ON public.bot_conversaciones FOR ALL TO service_role USING (true) WITH CHECK (true);

-- bot_mensajes
ALTER TABLE IF EXISTS public.bot_mensajes ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.bot_mensajes;
CREATE POLICY tenant_select ON public.bot_mensajes FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.bot_mensajes;
CREATE POLICY tenant_insert ON public.bot_mensajes FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.bot_mensajes;
CREATE POLICY service_all ON public.bot_mensajes FOR ALL TO service_role USING (true) WITH CHECK (true);

-- equipos_marcas (ELIMINADA en 0008)
ALTER TABLE IF EXISTS public.equipos_marcas ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.equipos_marcas;
CREATE POLICY tenant_select ON public.equipos_marcas FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.equipos_marcas;
CREATE POLICY tenant_insert ON public.equipos_marcas FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.equipos_marcas;
CREATE POLICY tenant_update ON public.equipos_marcas FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.equipos_marcas;
CREATE POLICY tenant_delete ON public.equipos_marcas FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.equipos_marcas;
CREATE POLICY service_all ON public.equipos_marcas FOR ALL TO service_role USING (true) WITH CHECK (true);

-- accesorios_colores (ELIMINADA en 0008)
ALTER TABLE IF EXISTS public.accesorios_colores ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.accesorios_colores;
CREATE POLICY tenant_select ON public.accesorios_colores FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.accesorios_colores;
CREATE POLICY tenant_insert ON public.accesorios_colores FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.accesorios_colores;
CREATE POLICY tenant_update ON public.accesorios_colores FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.accesorios_colores;
CREATE POLICY tenant_delete ON public.accesorios_colores FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.accesorios_colores;
CREATE POLICY service_all ON public.accesorios_colores FOR ALL TO service_role USING (true) WITH CHECK (true);

-- accesorios_marcas (ELIMINADA en 0008)
ALTER TABLE IF EXISTS public.accesorios_marcas ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.accesorios_marcas;
CREATE POLICY tenant_select ON public.accesorios_marcas FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.accesorios_marcas;
CREATE POLICY tenant_insert ON public.accesorios_marcas FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.accesorios_marcas;
CREATE POLICY tenant_update ON public.accesorios_marcas FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.accesorios_marcas;
CREATE POLICY tenant_delete ON public.accesorios_marcas FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.accesorios_marcas;
CREATE POLICY service_all ON public.accesorios_marcas FOR ALL TO service_role USING (true) WITH CHECK (true);

-- accesorios_subcategorias (ELIMINADA en 0008)
ALTER TABLE IF EXISTS public.accesorios_subcategorias ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.accesorios_subcategorias;
CREATE POLICY tenant_select ON public.accesorios_subcategorias FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.accesorios_subcategorias;
CREATE POLICY tenant_insert ON public.accesorios_subcategorias FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.accesorios_subcategorias;
CREATE POLICY tenant_update ON public.accesorios_subcategorias FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.accesorios_subcategorias;
CREATE POLICY tenant_delete ON public.accesorios_subcategorias FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.accesorios_subcategorias;
CREATE POLICY service_all ON public.accesorios_subcategorias FOR ALL TO service_role USING (true) WITH CHECK (true);

-- pantallas_modelos (ELIMINADA en 0008)
ALTER TABLE IF EXISTS public.pantallas_modelos ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.pantallas_modelos;
CREATE POLICY tenant_select ON public.pantallas_modelos FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.pantallas_modelos;
CREATE POLICY tenant_insert ON public.pantallas_modelos FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.pantallas_modelos;
CREATE POLICY tenant_update ON public.pantallas_modelos FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.pantallas_modelos;
CREATE POLICY tenant_delete ON public.pantallas_modelos FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.pantallas_modelos;
CREATE POLICY service_all ON public.pantallas_modelos FOR ALL TO service_role USING (true) WITH CHECK (true);

-- pantallas_modelos_tecnicos (ELIMINADA en 0008)
ALTER TABLE IF EXISTS public.pantallas_modelos_tecnicos ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.pantallas_modelos_tecnicos;
CREATE POLICY tenant_select ON public.pantallas_modelos_tecnicos FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.pantallas_modelos_tecnicos;
CREATE POLICY tenant_insert ON public.pantallas_modelos_tecnicos FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.pantallas_modelos_tecnicos;
CREATE POLICY tenant_update ON public.pantallas_modelos_tecnicos FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.pantallas_modelos_tecnicos;
CREATE POLICY tenant_delete ON public.pantallas_modelos_tecnicos FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.pantallas_modelos_tecnicos;
CREATE POLICY service_all ON public.pantallas_modelos_tecnicos FOR ALL TO service_role USING (true) WITH CHECK (true);

-- inv_accesorios
ALTER TABLE IF EXISTS public.inv_accesorios ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.inv_accesorios;
CREATE POLICY tenant_select ON public.inv_accesorios FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.inv_accesorios;
CREATE POLICY tenant_insert ON public.inv_accesorios FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.inv_accesorios;
CREATE POLICY tenant_update ON public.inv_accesorios FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.inv_accesorios;
CREATE POLICY tenant_delete ON public.inv_accesorios FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.inv_accesorios;
CREATE POLICY service_all ON public.inv_accesorios FOR ALL TO service_role USING (true) WITH CHECK (true);

-- inv_baterias
ALTER TABLE IF EXISTS public.inv_baterias ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.inv_baterias;
CREATE POLICY tenant_select ON public.inv_baterias FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.inv_baterias;
CREATE POLICY tenant_insert ON public.inv_baterias FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.inv_baterias;
CREATE POLICY tenant_update ON public.inv_baterias FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.inv_baterias;
CREATE POLICY tenant_delete ON public.inv_baterias FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.inv_baterias;
CREATE POLICY service_all ON public.inv_baterias FOR ALL TO service_role USING (true) WITH CHECK (true);

-- inv_pantallas
ALTER TABLE IF EXISTS public.inv_pantallas ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.inv_pantallas;
CREATE POLICY tenant_select ON public.inv_pantallas FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.inv_pantallas;
CREATE POLICY tenant_insert ON public.inv_pantallas FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.inv_pantallas;
CREATE POLICY tenant_update ON public.inv_pantallas FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.inv_pantallas;
CREATE POLICY tenant_delete ON public.inv_pantallas FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.inv_pantallas;
CREATE POLICY service_all ON public.inv_pantallas FOR ALL TO service_role USING (true) WITH CHECK (true);

-- inv_servicios_generales
ALTER TABLE IF EXISTS public.inv_servicios_generales ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.inv_servicios_generales;
CREATE POLICY tenant_select ON public.inv_servicios_generales FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.inv_servicios_generales;
CREATE POLICY tenant_insert ON public.inv_servicios_generales FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.inv_servicios_generales;
CREATE POLICY tenant_update ON public.inv_servicios_generales FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.inv_servicios_generales;
CREATE POLICY tenant_delete ON public.inv_servicios_generales FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.inv_servicios_generales;
CREATE POLICY service_all ON public.inv_servicios_generales FOR ALL TO service_role USING (true) WITH CHECK (true);

-- inv_servicios_acciones
ALTER TABLE IF EXISTS public.inv_servicios_acciones ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.inv_servicios_acciones;
CREATE POLICY tenant_select ON public.inv_servicios_acciones FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.inv_servicios_acciones;
CREATE POLICY tenant_insert ON public.inv_servicios_acciones FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.inv_servicios_acciones;
CREATE POLICY tenant_update ON public.inv_servicios_acciones FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.inv_servicios_acciones;
CREATE POLICY tenant_delete ON public.inv_servicios_acciones FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.inv_servicios_acciones;
CREATE POLICY service_all ON public.inv_servicios_acciones FOR ALL TO service_role USING (true) WITH CHECK (true);

-- inv_embeddings
ALTER TABLE IF EXISTS public.inv_embeddings ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.inv_embeddings;
CREATE POLICY tenant_select ON public.inv_embeddings FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.inv_embeddings;
CREATE POLICY tenant_insert ON public.inv_embeddings FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.inv_embeddings;
CREATE POLICY tenant_update ON public.inv_embeddings FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.inv_embeddings;
CREATE POLICY tenant_delete ON public.inv_embeddings FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.inv_embeddings;
CREATE POLICY service_all ON public.inv_embeddings FOR ALL TO service_role USING (true) WITH CHECK (true);

-- ai_analisis_historial
ALTER TABLE IF EXISTS public.ai_analisis_historial ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.ai_analisis_historial;
CREATE POLICY tenant_select ON public.ai_analisis_historial FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.ai_analisis_historial;
CREATE POLICY tenant_insert ON public.ai_analisis_historial FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.ai_analisis_historial;
CREATE POLICY tenant_update ON public.ai_analisis_historial FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.ai_analisis_historial;
CREATE POLICY tenant_delete ON public.ai_analisis_historial FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.ai_analisis_historial;
CREATE POLICY service_all ON public.ai_analisis_historial FOR ALL TO service_role USING (true) WITH CHECK (true);

-- whatsapp_templates
ALTER TABLE IF EXISTS public.whatsapp_templates ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS service_all ON public.whatsapp_templates;
CREATE POLICY service_all ON public.whatsapp_templates FOR ALL TO service_role USING (true) WITH CHECK (true);

-- soporte (ELIMINADA en 0008 — reemplazada por bot_conversaciones)
ALTER TABLE IF EXISTS public.soporte ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.soporte;
CREATE POLICY tenant_select ON public.soporte FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.soporte;
CREATE POLICY tenant_insert ON public.soporte FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.soporte;
CREATE POLICY tenant_update ON public.soporte FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.soporte;
CREATE POLICY tenant_delete ON public.soporte FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.soporte;
CREATE POLICY service_all ON public.soporte FOR ALL TO service_role USING (true) WITH CHECK (true);

-- garantias (ELIMINADA en 0008 — reemplazada por reparacion_garantias)
ALTER TABLE IF EXISTS public.garantias ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.garantias;
CREATE POLICY tenant_select ON public.garantias FOR SELECT USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_insert ON public.garantias;
CREATE POLICY tenant_insert ON public.garantias FOR INSERT WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_update ON public.garantias;
CREATE POLICY tenant_update ON public.garantias FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.garantias;
CREATE POLICY tenant_delete ON public.garantias FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.garantias;
CREATE POLICY service_all ON public.garantias FOR ALL TO service_role USING (true) WITH CHECK (true);

-- plantillas (ELIMINADA en 0008 — reemplazada por whatsapp_templates + configuracion_mensajes)
ALTER TABLE IF EXISTS public.plantillas ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS tenant_select ON public.plantillas;
CREATE POLICY tenant_select ON public.plantillas FOR SELECT USING (tenant_id = current_tenant_id() OR tenant_id IS NULL);
DROP POLICY IF EXISTS tenant_insert ON public.plantillas;
CREATE POLICY tenant_insert ON public.plantillas FOR INSERT WITH CHECK (tenant_id = current_tenant_id() OR tenant_id IS NULL);
DROP POLICY IF EXISTS tenant_update ON public.plantillas;
CREATE POLICY tenant_update ON public.plantillas FOR UPDATE USING (tenant_id = current_tenant_id()) WITH CHECK (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS tenant_delete ON public.plantillas;
CREATE POLICY tenant_delete ON public.plantillas FOR DELETE USING (tenant_id = current_tenant_id());
DROP POLICY IF EXISTS service_all ON public.plantillas;
CREATE POLICY service_all ON public.plantillas FOR ALL TO service_role USING (true) WITH CHECK (true);

-- ═══════════════════════════════════════════════════════
-- TABLAS GLOBALES (sin tenant_id): lectura publica, escritura admin
-- ═══════════════════════════════════════════════════════

-- roles
ALTER TABLE IF EXISTS public.roles ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS roles_select ON public.roles;
CREATE POLICY roles_select ON public.roles FOR SELECT USING (true);
DROP POLICY IF EXISTS roles_admin ON public.roles;
CREATE POLICY roles_admin ON public.roles FOR ALL
  USING (auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin')
  WITH CHECK (auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin');
DROP POLICY IF EXISTS service_all ON public.roles;
CREATE POLICY service_all ON public.roles FOR ALL TO service_role USING (true) WITH CHECK (true);

-- permisos
ALTER TABLE IF EXISTS public.permisos ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS permisos_select ON public.permisos;
CREATE POLICY permisos_select ON public.permisos FOR SELECT USING (true);
DROP POLICY IF EXISTS permisos_admin ON public.permisos;
CREATE POLICY permisos_admin ON public.permisos FOR ALL
  USING (auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin')
  WITH CHECK (auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin');
DROP POLICY IF EXISTS service_all ON public.permisos;
CREATE POLICY service_all ON public.permisos FOR ALL TO service_role USING (true) WITH CHECK (true);

-- role_permiso
ALTER TABLE IF EXISTS public.role_permiso ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS role_permiso_select ON public.role_permiso;
CREATE POLICY role_permiso_select ON public.role_permiso FOR SELECT USING (true);
DROP POLICY IF EXISTS role_permiso_admin ON public.role_permiso;
CREATE POLICY role_permiso_admin ON public.role_permiso FOR ALL
  USING (auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin')
  WITH CHECK (auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin');
DROP POLICY IF EXISTS service_all ON public.role_permiso;
CREATE POLICY service_all ON public.role_permiso FOR ALL TO service_role USING (true) WITH CHECK (true);

-- reparacion_estados
ALTER TABLE IF EXISTS public.reparacion_estados ENABLE ROW LEVEL SECURITY;
DROP POLICY IF EXISTS reparacion_estados_select ON public.reparacion_estados;
CREATE POLICY reparacion_estados_select ON public.reparacion_estados FOR SELECT USING (true);
DROP POLICY IF EXISTS reparacion_estados_admin ON public.reparacion_estados;
CREATE POLICY reparacion_estados_admin ON public.reparacion_estados FOR ALL
  USING (auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin')
  WITH CHECK (auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin');
DROP POLICY IF EXISTS service_all ON public.reparacion_estados;
CREATE POLICY service_all ON public.reparacion_estados FOR ALL TO service_role USING (true) WITH CHECK (true);

COMMIT;
