-- 0016: RPC para leer árbol de estados con security definer
-- Necesario porque el JWT del usuario puede no tener app_metadata.tenant_id,
-- lo que causa que el RLS de estados_config devuelva vacío al consultar via PostgREST directo.
-- Al igual que rpc_reparaciones_panel_paginated, esta función bypasea RLS
-- y filtra por p_tenant_id recibido desde PHP (validado en sesión del servidor).

create or replace function public.rpc_estados_tree(p_tenant_id bigint)
returns jsonb
language plpgsql stable security definer
set search_path = public
as $$
declare
    v_rows jsonb;
begin
    select coalesce(jsonb_agg(row_to_json(e)::jsonb order by e.orden asc, e.created_at asc), '[]'::jsonb)
    into v_rows
    from public.estados_config e
    where e.tenant_id = p_tenant_id
      and e.activo = true;

    return v_rows;
end;
$$;
