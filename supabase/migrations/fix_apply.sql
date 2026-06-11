-- ============================================================================
-- Fix acumulado: rpc_ejecutar_consulta_segura + external_api_logs + whatsapp_templates
-- ============================================================================
BEGIN;

-- ═══════════════════════════════════════════════════════
-- PASO 2: rpc_ejecutar_consulta_segura (fixed)
-- ═══════════════════════════════════════════════════════
CREATE OR REPLACE FUNCTION public.rpc_ejecutar_consulta_segura(p_sql text)
RETURNS jsonb
LANGUAGE plpgsql SECURITY DEFINER SET search_path = ''
AS $$
DECLARE
  v_rows jsonb;
  v_cols jsonb;
BEGIN
  IF NOT (lower(btrim(p_sql)) LIKE 'select%') THEN
    RETURN jsonb_build_object('error', 'Solo consultas SELECT permitidas');
  END IF;
  p_sql := regexp_replace(p_sql, ';\s*$', '');
  BEGIN
    EXECUTE format(
      'WITH result AS (%s) SELECT coalesce(jsonb_agg(row_to_json(r)), ''[]''::jsonb) FROM result r',
      p_sql
    ) INTO v_rows;
    EXECUTE format(
      'WITH result AS (%s) SELECT coalesce(jsonb_agg(a.key), ''[]''::jsonb) FROM result r, LATERAL jsonb_each(to_jsonb(r)) a LIMIT 1',
      p_sql
    ) INTO v_cols;
    RETURN jsonb_build_object(
      'columns', coalesce(v_cols, '[]'::jsonb),
      'rows', coalesce(v_rows, '[]'::jsonb),
      'row_count', jsonb_array_length(coalesce(v_rows, '[]'::jsonb))
    );
  EXCEPTION WHEN OTHERS THEN
    RETURN jsonb_build_object('error', SQLERRM);
  END;
END;
$$;

-- ═══════════════════════════════════════════════════════
-- PASO 5: external_api_logs + tenant_id
-- ═══════════════════════════════════════════════════════
ALTER TABLE public.external_api_logs ADD COLUMN IF NOT EXISTS tenant_id bigint REFERENCES public.tenants(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_external_api_logs_tenant ON public.external_api_logs(tenant_id);

DROP POLICY IF EXISTS p_external_api_logs_read ON public.external_api_logs;
DROP POLICY IF EXISTS p_external_api_logs_write ON public.external_api_logs;
DROP POLICY IF EXISTS service_all ON public.external_api_logs;

CREATE POLICY external_api_logs_tenant_select ON public.external_api_logs
  FOR SELECT TO authenticated USING (tenant_id = (auth.jwt() -> 'app_metadata' ->> 'tenant_id')::integer);
CREATE POLICY external_api_logs_tenant_insert ON public.external_api_logs
  FOR INSERT TO authenticated WITH CHECK (tenant_id = (auth.jwt() -> 'app_metadata' ->> 'tenant_id')::integer);
CREATE POLICY external_api_logs_service_all ON public.external_api_logs
  FOR ALL TO service_role USING (true) WITH CHECK (true);

-- ═══════════════════════════════════════════════════════
-- PASO 6: whatsapp_templates RLS
-- ═══════════════════════════════════════════════════════
DROP POLICY IF EXISTS p_whatsapp_templates_read ON public.whatsapp_templates;
DROP POLICY IF EXISTS p_whatsapp_templates_write ON public.whatsapp_templates;
DROP POLICY IF EXISTS service_all ON public.whatsapp_templates;

CREATE POLICY whatsapp_templates_select ON public.whatsapp_templates
  FOR SELECT TO authenticated USING (true);
CREATE POLICY whatsapp_templates_admin ON public.whatsapp_templates
  FOR ALL TO authenticated
  USING (coalesce((auth.jwt() -> 'app_metadata' ->> 'rol'), '') = 'admin')
  WITH CHECK (coalesce((auth.jwt() -> 'app_metadata' ->> 'rol'), '') = 'admin');
CREATE POLICY whatsapp_templates_service_all ON public.whatsapp_templates
  FOR ALL TO service_role USING (true) WITH CHECK (true);

COMMIT;
