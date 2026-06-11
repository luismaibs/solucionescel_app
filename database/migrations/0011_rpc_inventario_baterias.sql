-- =============================================================================
-- Migracion 0010: RPC paginado para inv_baterias
-- Unifica el patron de consulta con las demas categorias de inventario.
-- =============================================================================
begin;

-- rpc_baterias_paginado
create or replace function public.rpc_baterias_paginado(
  p_tenant_id bigint, p_offset int, p_limit int,
  out total_count bigint, out rows jsonb
)
language plpgsql stable security definer set search_path = ''
as $$
begin
  select count(*)::bigint into total_count
  from public.inv_baterias
  where tenant_id = p_tenant_id and deleted_at is null;

  select coalesce(jsonb_agg(row_to_json(t) order by t.marca asc, t.created_at desc), '[]'::jsonb) into rows
  from (
    select * from public.inv_baterias
    where tenant_id = p_tenant_id and deleted_at is null
    limit p_limit offset p_offset
  ) t;
end;
$$;

commit;
