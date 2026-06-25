-- =============================================================================
-- Migracion 0020: Corregir listado de pantallas con catalogos compartidos
-- =============================================================================
begin;

create or replace function public.rpc_pantallas_paginado(
  p_tenant_id bigint, p_offset int, p_limit int,
  out total_count bigint, out rows jsonb
)
language plpgsql stable security definer set search_path = ''
as $$
begin
  select count(*)::bigint into total_count
  from public.inv_pantallas
  where tenant_id = p_tenant_id and deleted_at is null;

  select coalesce(jsonb_agg(row_to_json(t) order by t.modelo_nombre asc, t.calidad asc, t.created_at desc), '[]'::jsonb) into rows
  from (
    select p.*,
      m.nombre as modelo_nombre,
      mt.nombre as modelo_tecnico_nombre
    from public.inv_pantallas p
    left join public.modelos m
      on m.tenant_id = p.tenant_id and m.id = p.modelo_id
    left join public.modelos mt
      on mt.tenant_id = p.tenant_id and mt.id = p.modelo_tecnico_id
    where p.tenant_id = p_tenant_id and p.deleted_at is null
    limit p_limit offset p_offset
  ) t;
end;
$$;

commit;
