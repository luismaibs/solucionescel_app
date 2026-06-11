-- 0009: RPC para KPIs del panel de equipos
-- Reemplaza la carga completa de reparaciones con agregados COUNT
begin;

create or replace function public.rpc_reparaciones_kpis(p_tenant_id bigint)
returns jsonb
language sql
security definer
set search_path = public
as $$
  select jsonb_build_object(
    'activos', coalesce((select count(*) from public.reparaciones
      where tenant_id = p_tenant_id and deleted_at is null
      and estado not in ('entregado', 'inactivo')), 0),
    'listos', coalesce((select count(*) from public.reparaciones
      where tenant_id = p_tenant_id and deleted_at is null
      and estado in ('listo', 'listo_sin_garantia')), 0),
    'taller', coalesce((select count(*) from public.reparaciones
      where tenant_id = p_tenant_id and deleted_at is null
      and estado = 'en_taller'), 0),
    'viejos', coalesce((select count(*) from public.reparaciones
      where tenant_id = p_tenant_id and deleted_at is null
      and estado in ('listo', 'listo_sin_garantia')
      and fecha_listo is not null
      and fecha_listo <= (now() - interval '90 days')), 0)
  );
$$;

commit;
