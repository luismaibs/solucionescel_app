-- =============================================================================
-- Migracion 0010: Optimizacion y correccion Mes Azul
-- =============================================================================
begin;

-- 1. RPC atomico para finalizacion (ambos UPDATEs en una transaccion)
create or replace function public.rpc_mes_azul_finalizar(
  p_tenant_id bigint, p_reparacion_id bigint
)
returns void
language plpgsql security definer
set search_path = ''
as $$
begin
  update public.reparaciones
  set estado = 'inactivo'
  where tenant_id = p_tenant_id and id = p_reparacion_id;

  update public.reparacion_garantias
  set mes_azul_final_enviado = now(),
      mes_azul_estado = 'inactivado',
      mes_azul_fecha_inactivacion = now(),
      inicio_garantia_reactivado = false
  where tenant_id = p_tenant_id and reparacion_id = p_reparacion_id;
end;
$$;

-- 2. RPCs ligeros para el batch diario (solo retornan IDs)
create or replace function public.rpc_mes_azul_inicio_ids(p_tenant_id bigint)
returns table(id bigint)
language sql stable security definer
set search_path = ''
as $$
  select r.id
  from public.reparaciones r
  left join public.reparacion_garantias g
    on g.reparacion_id = r.id and g.tenant_id = r.tenant_id
  where r.tenant_id = p_tenant_id and r.deleted_at is null
    and r.estado = 'listo' and r.fecha_listo is not null
    and (g.mes_azul_inicio_enviado is null or g.mes_azul_estado = 'no_aplica')
    and r.fecha_listo <= now() - interval '90 days'
  order by r.fecha_listo asc;
$$;

create or replace function public.rpc_mes_azul_final_ids(p_tenant_id bigint)
returns table(id bigint)
language sql stable security definer
set search_path = ''
as $$
  select r.id
  from public.reparaciones r
  inner join public.reparacion_garantias g
    on g.reparacion_id = r.id and g.tenant_id = r.tenant_id
  where r.tenant_id = p_tenant_id and r.deleted_at is null
    and g.mes_azul_inicio_enviado is not null
    and g.mes_azul_final_enviado is null
    and g.mes_azul_estado = 'esperando_final'
    and g.mes_azul_inicio_enviado <= now() - interval '5 days'
  order by g.mes_azul_inicio_enviado asc;
$$;

-- 3. rpc_dispositivos_90dias: anade dias_transcurridos calculado en SQL
drop function if exists public.rpc_dispositivos_90dias(bigint);
create or replace function public.rpc_dispositivos_90dias(p_tenant_id bigint)
returns table(
  id bigint, folio_publico text, cliente_nombre text,
  equipo_marca text, equipo_modelo text,
  fecha_ingreso timestamptz, fecha_listo timestamptz,
  dias_transcurridos int,
  mes_azul_inicio_enviado timestamptz, mes_azul_final_enviado timestamptz,
  mes_azul_estado text
)
language sql stable security definer
set search_path = ''
as $$
  select r.id, r.folio_publico,
    trim(coalesce(c.nombre, '') || ' ' || coalesce(c.apellido, '')) as cliente_nombre,
    r.equipo_marca, r.equipo_modelo, r.fecha_ingreso, r.fecha_listo,
    floor(extract(epoch from (now() - r.fecha_listo)) / 86400)::int as dias_transcurridos,
    g.mes_azul_inicio_enviado, g.mes_azul_final_enviado, g.mes_azul_estado
  from public.reparaciones r
  inner join public.clientes c
    on c.id = r.cliente_id and c.tenant_id = r.tenant_id and c.deleted_at is null
  left join public.reparacion_garantias g
    on g.reparacion_id = r.id and g.tenant_id = r.tenant_id
  where r.tenant_id = p_tenant_id and r.deleted_at is null
    and r.estado = 'listo'
    and r.fecha_listo is not null
    and r.fecha_listo <= now() - interval '90 days'
  order by r.fecha_listo asc;
$$;

-- 4. Eliminar valor muerto 'pendiente_inicio' del CHECK constraint
alter table public.reparacion_garantias
  drop constraint if exists reparacion_garantias_mes_azul_estado_check;

alter table public.reparacion_garantias
  add constraint reparacion_garantias_mes_azul_estado_check
  check (mes_azul_estado in ('no_aplica', 'esperando_final', 'inactivado'));

commit;
