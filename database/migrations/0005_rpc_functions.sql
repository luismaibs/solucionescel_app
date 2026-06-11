-- =============================================================================
-- Migracion 0005: Funciones PostgreSQL expuestas via PostgREST RPC
-- Cada funcion corresponde a una query con JOINs de los repositorios PHP.
-- =============================================================================
begin;

-- =============================================================================
-- REPARACIONES
-- =============================================================================

-- rpc_reparaciones_panel: findAllForPanel()
create or replace function public.rpc_reparaciones_panel(p_tenant_id bigint)
returns table(
  id bigint, tenant_id bigint, cliente_id bigint, folio_publico text,
  equipo_marca text, equipo_marca_id bigint, equipo_modelo text,
  falla_reportada text, estado text, fecha_ingreso timestamptz,
  created_by_user_id bigint, costo_final numeric(10,2),
  deleted_at timestamptz, fecha_listo timestamptz,
  created_at timestamptz, updated_at timestamptz,
  cliente_nombre text, telefono text,
  tipo_garantia text, inicio_garantia_reactivado boolean,
  mes_azul_inicio_enviado timestamptz, mes_azul_final_enviado timestamptz,
  mes_azul_estado text, mes_azul_fecha_inactivacion timestamptz
)
language sql stable security definer
set search_path = ''
as $$
  select r.*,
    trim(coalesce(c.nombre, '') || ' ' || coalesce(c.apellido, '')) as cliente_nombre,
    c.telefono,
    g.tipo_garantia, g.inicio_garantia_reactivado,
    g.mes_azul_inicio_enviado, g.mes_azul_final_enviado,
    g.mes_azul_estado, g.mes_azul_fecha_inactivacion
  from public.reparaciones r
  inner join public.clientes c
    on c.id = r.cliente_id and c.tenant_id = r.tenant_id and c.deleted_at is null
  left join public.reparacion_garantias g
    on g.reparacion_id = r.id and g.tenant_id = r.tenant_id
  where r.tenant_id = p_tenant_id and r.deleted_at is null
  order by
    case when r.estado in ('entregado','inactivo')
      or g.mes_azul_estado = 'inactivado' then 1 else 0 end asc,
    r.id desc;
$$;

-- rpc_reparaciones_panel_paginated: findForPanelPaginated()
create or replace function public.rpc_reparaciones_panel_paginated(
  p_tenant_id bigint, p_offset int, p_limit int,
  out total_count bigint, out rows jsonb
)
language plpgsql stable security definer
set search_path = ''
as $$
begin
  select count(*)::bigint into total_count
  from public.reparaciones
  where tenant_id = p_tenant_id and deleted_at is null;

  select coalesce(jsonb_agg(row_to_json(t)), '[]'::jsonb) into rows
  from (
    select r.*,
      trim(coalesce(c.nombre, '') || ' ' || coalesce(c.apellido, '')) as cliente_nombre,
      c.telefono,
      g.tipo_garantia, g.inicio_garantia_reactivado,
      g.mes_azul_inicio_enviado, g.mes_azul_final_enviado,
      g.mes_azul_estado, g.mes_azul_fecha_inactivacion
    from public.reparaciones r
    inner join public.clientes c
      on c.id = r.cliente_id and c.tenant_id = r.tenant_id and c.deleted_at is null
    left join public.reparacion_garantias g
      on g.reparacion_id = r.id and g.tenant_id = r.tenant_id
    where r.tenant_id = p_tenant_id and r.deleted_at is null
    order by
      case when r.estado in ('entregado','inactivo')
        or g.mes_azul_estado = 'inactivado' then 1 else 0 end asc,
      r.id desc
    limit p_limit offset p_offset
  ) t;
end;
$$;

-- rpc_reparacion_by_id: findById()
create or replace function public.rpc_reparacion_by_id(p_tenant_id bigint, p_id bigint)
returns table(
  id bigint, tenant_id bigint, cliente_id bigint, folio_publico text,
  equipo_marca text, equipo_marca_id bigint, equipo_modelo text,
  falla_reportada text, estado text, fecha_ingreso timestamptz,
  created_by_user_id bigint, costo_final numeric(10,2),
  deleted_at timestamptz, fecha_listo timestamptz,
  created_at timestamptz, updated_at timestamptz,
  cliente_nombre text, telefono text,
  tipo_garantia text, inicio_garantia_reactivado boolean,
  mes_azul_inicio_enviado timestamptz, mes_azul_final_enviado timestamptz,
  mes_azul_estado text, mes_azul_fecha_inactivacion timestamptz
)
language sql stable security definer
set search_path = ''
as $$
  select r.*,
    trim(coalesce(c.nombre, '') || ' ' || coalesce(c.apellido, '')) as cliente_nombre,
    c.telefono,
    g.tipo_garantia, g.inicio_garantia_reactivado,
    g.mes_azul_inicio_enviado, g.mes_azul_final_enviado,
    g.mes_azul_estado, g.mes_azul_fecha_inactivacion
  from public.reparaciones r
  inner join public.clientes c
    on c.id = r.cliente_id and c.tenant_id = r.tenant_id and c.deleted_at is null
  left join public.reparacion_garantias g
    on g.reparacion_id = r.id and g.tenant_id = r.tenant_id
  where r.tenant_id = p_tenant_id and r.id = p_id
  limit 1;
$$;

-- rpc_reparacion_by_folio: findByFolioActivo()
create or replace function public.rpc_reparacion_by_folio(p_tenant_id bigint, p_folio text)
returns table(
  id bigint, tenant_id bigint, cliente_id bigint, folio_publico text,
  equipo_marca text, equipo_marca_id bigint, equipo_modelo text,
  falla_reportada text, estado text, fecha_ingreso timestamptz,
  created_by_user_id bigint, costo_final numeric(10,2),
  deleted_at timestamptz, fecha_listo timestamptz,
  created_at timestamptz, updated_at timestamptz,
  cliente_nombre text, telefono text,
  tipo_garantia text, inicio_garantia_reactivado boolean,
  mes_azul_inicio_enviado timestamptz, mes_azul_final_enviado timestamptz,
  mes_azul_estado text, mes_azul_fecha_inactivacion timestamptz
)
language sql stable security definer
set search_path = ''
as $$
  select r.*,
    trim(coalesce(c.nombre, '') || ' ' || coalesce(c.apellido, '')) as cliente_nombre,
    c.telefono,
    g.tipo_garantia, g.inicio_garantia_reactivado,
    g.mes_azul_inicio_enviado, g.mes_azul_final_enviado,
    g.mes_azul_estado, g.mes_azul_fecha_inactivacion
  from public.reparaciones r
  inner join public.clientes c
    on c.id = r.cliente_id and c.tenant_id = r.tenant_id and c.deleted_at is null
  left join public.reparacion_garantias g
    on g.reparacion_id = r.id and g.tenant_id = r.tenant_id
  where r.tenant_id = p_tenant_id and r.folio_publico = p_folio
  limit 1;
$$;

-- rpc_proximo_folio: getProximoFolio()
create or replace function public.rpc_proximo_folio(p_tenant_id bigint)
returns text
language sql stable security definer
set search_path = ''
as $$
  select 'SC-' || lpad((coalesce(max(id), 0) + 1)::text, 4, '0')
  from public.reparaciones
  where tenant_id = p_tenant_id;
$$;

-- rpc_marcas_modelos_distinct: findDistinctMarcasModelos()
create or replace function public.rpc_marcas_modelos_distinct(p_tenant_id bigint)
returns table(equipo_marca text, equipo_modelo text)
language sql stable security definer
set search_path = ''
as $$
  select distinct r.equipo_marca, r.equipo_modelo
  from public.reparaciones r
  where r.tenant_id = p_tenant_id
    and r.equipo_marca is not null
    and r.equipo_marca != ''
    and r.deleted_at is null
  order by r.equipo_marca asc, r.equipo_modelo asc;
$$;

-- =============================================================================
-- MES AZUL
-- =============================================================================

-- rpc_dispositivos_90dias: findDispositivosCon90DiasOmas()
create or replace function public.rpc_dispositivos_90dias(p_tenant_id bigint)
returns table(
  id bigint, folio_publico text, cliente_nombre text,
  equipo_marca text, equipo_modelo text,
  fecha_ingreso timestamptz, fecha_listo timestamptz,
  mes_azul_inicio_enviado timestamptz, mes_azul_final_enviado timestamptz,
  mes_azul_estado text
)
language sql stable security definer
set search_path = ''
as $$
  select r.id, r.folio_publico,
    trim(coalesce(c.nombre, '') || ' ' || coalesce(c.apellido, '')) as cliente_nombre,
    r.equipo_marca, r.equipo_modelo, r.fecha_ingreso, r.fecha_listo,
    g.mes_azul_inicio_enviado, g.mes_azul_final_enviado, g.mes_azul_estado
  from public.reparaciones r
  inner join public.clientes c
    on c.id = r.cliente_id and c.tenant_id = r.tenant_id and c.deleted_at is null
  left join public.reparacion_garantias g
    on g.reparacion_id = r.id and g.tenant_id = r.tenant_id
  where r.tenant_id = p_tenant_id and r.deleted_at is null
    and r.estado in ('listo')
    and r.fecha_listo is not null
    and r.fecha_listo <= now() - interval '90 days'
  order by r.fecha_listo asc;
$$;

-- rpc_equipos_mes_azul_inicio: findEquiposParaMesAzulInicio()
create or replace function public.rpc_equipos_mes_azul_inicio(p_tenant_id bigint)
returns table(
  id bigint, tenant_id bigint, cliente_id bigint, folio_publico text,
  equipo_marca text, equipo_marca_id bigint, equipo_modelo text,
  falla_reportada text, estado text, fecha_ingreso timestamptz,
  created_by_user_id bigint, costo_final numeric(10,2),
  deleted_at timestamptz, fecha_listo timestamptz,
  created_at timestamptz, updated_at timestamptz,
  cliente_nombre text, telefono text,
  tipo_garantia text, inicio_garantia_reactivado boolean,
  mes_azul_inicio_enviado timestamptz, mes_azul_final_enviado timestamptz,
  mes_azul_estado text, mes_azul_fecha_inactivacion timestamptz
)
language sql stable security definer
set search_path = ''
as $$
  select r.*,
    trim(coalesce(c.nombre, '') || ' ' || coalesce(c.apellido, '')) as cliente_nombre,
    c.telefono,
    g.tipo_garantia, g.inicio_garantia_reactivado,
    g.mes_azul_inicio_enviado, g.mes_azul_final_enviado,
    g.mes_azul_estado, g.mes_azul_fecha_inactivacion
  from public.reparaciones r
  inner join public.clientes c
    on c.id = r.cliente_id and c.tenant_id = r.tenant_id and c.deleted_at is null
  left join public.reparacion_garantias g
    on g.reparacion_id = r.id and g.tenant_id = r.tenant_id
  where r.tenant_id = p_tenant_id and r.deleted_at is null
    and r.estado in ('listo')
    and r.fecha_listo is not null
    and (g.mes_azul_inicio_enviado is null or g.mes_azul_estado = 'no_aplica')
    and r.fecha_listo <= now() - interval '90 days'
  order by r.fecha_listo asc;
$$;

-- rpc_equipos_mes_azul_final: findEquiposParaMesAzulFinal()
create or replace function public.rpc_equipos_mes_azul_final(p_tenant_id bigint)
returns table(
  id bigint, tenant_id bigint, cliente_id bigint, folio_publico text,
  equipo_marca text, equipo_marca_id bigint, equipo_modelo text,
  falla_reportada text, estado text, fecha_ingreso timestamptz,
  created_by_user_id bigint, costo_final numeric(10,2),
  deleted_at timestamptz, fecha_listo timestamptz,
  created_at timestamptz, updated_at timestamptz,
  cliente_nombre text, telefono text,
  tipo_garantia text, inicio_garantia_reactivado boolean,
  mes_azul_inicio_enviado timestamptz, mes_azul_final_enviado timestamptz,
  mes_azul_estado text, mes_azul_fecha_inactivacion timestamptz
)
language sql stable security definer
set search_path = ''
as $$
  select r.*,
    trim(coalesce(c.nombre, '') || ' ' || coalesce(c.apellido, '')) as cliente_nombre,
    c.telefono,
    g.tipo_garantia, g.inicio_garantia_reactivado,
    g.mes_azul_inicio_enviado, g.mes_azul_final_enviado,
    g.mes_azul_estado, g.mes_azul_fecha_inactivacion
  from public.reparaciones r
  inner join public.clientes c
    on c.id = r.cliente_id and c.tenant_id = r.tenant_id and c.deleted_at is null
  inner join public.reparacion_garantias g
    on g.reparacion_id = r.id and g.tenant_id = r.tenant_id
  where r.tenant_id = p_tenant_id and r.deleted_at is null
    and g.mes_azul_inicio_enviado is not null
    and g.mes_azul_final_enviado is null
    and g.mes_azul_estado = 'esperando_final'
    and g.mes_azul_inicio_enviado <= now() - interval '5 days'
  order by g.mes_azul_inicio_enviado asc;
$$;

-- rpc_mes_azul_activo: findDispositivosMesAzulActivo()
create or replace function public.rpc_mes_azul_activo(p_tenant_id bigint)
returns table(
  id bigint, folio_publico text, cliente_nombre text,
  equipo_marca text, equipo_modelo text,
  fecha_listo timestamptz, mes_azul_inicio_enviado timestamptz,
  mes_azul_estado text
)
language sql stable security definer
set search_path = ''
as $$
  select r.id, r.folio_publico,
    trim(coalesce(c.nombre, '') || ' ' || coalesce(c.apellido, '')) as cliente_nombre,
    r.equipo_marca, r.equipo_modelo, r.fecha_listo,
    g.mes_azul_inicio_enviado, g.mes_azul_estado
  from public.reparaciones r
  inner join public.clientes c
    on c.id = r.cliente_id and c.tenant_id = r.tenant_id and c.deleted_at is null
  inner join public.reparacion_garantias g
    on g.reparacion_id = r.id and g.tenant_id = r.tenant_id
  where r.tenant_id = p_tenant_id and r.deleted_at is null
    and g.mes_azul_estado = 'esperando_final'
    and g.mes_azul_final_enviado is null
  order by g.mes_azul_inicio_enviado asc;
$$;

-- rpc_mes_azul_historial: findDispositivosMesAzulHistorial()
create or replace function public.rpc_mes_azul_historial(p_tenant_id bigint)
returns table(
  id bigint, folio_publico text, cliente_nombre text,
  equipo_marca text, equipo_modelo text, fecha_listo timestamptz,
  mes_azul_inicio_enviado timestamptz, mes_azul_final_enviado timestamptz,
  mes_azul_fecha_inactivacion timestamptz, mes_azul_estado text
)
language sql stable security definer
set search_path = ''
as $$
  select r.id, r.folio_publico,
    trim(coalesce(c.nombre, '') || ' ' || coalesce(c.apellido, '')) as cliente_nombre,
    r.equipo_marca, r.equipo_modelo, r.fecha_listo,
    g.mes_azul_inicio_enviado, g.mes_azul_final_enviado,
    g.mes_azul_fecha_inactivacion, g.mes_azul_estado
  from public.reparaciones r
  inner join public.clientes c
    on c.id = r.cliente_id and c.tenant_id = r.tenant_id and c.deleted_at is null
  inner join public.reparacion_garantias g
    on g.reparacion_id = r.id and g.tenant_id = r.tenant_id
  where r.tenant_id = p_tenant_id and r.deleted_at is null
    and g.mes_azul_estado = 'inactivado'
  order by g.mes_azul_fecha_inactivacion desc;
$$;

-- =============================================================================
-- CLIENTES
-- =============================================================================

-- rpc_clientes_paginated: findPaginated() con busqueda y conteos
create or replace function public.rpc_clientes_paginated(
  p_tenant_id bigint, p_offset int, p_limit int, p_search text default '',
  out total_count bigint, out rows jsonb
)
language plpgsql stable security definer
set search_path = ''
as $$
declare
  v_where text := 'c.tenant_id = $1 and c.deleted_at is null';
  v_like text;
begin
  if p_search != '' then
    v_like := '%' || p_search || '%';
    v_where := v_where || format(
      ' and (c.nombre ilike %L or c.apellido ilike %L or c.telefono ilike %L
         or c.correo ilike %L or (c.nombre || '' '' || c.apellido) ilike %L)',
      v_like, v_like, v_like, v_like, v_like
    );
  end if;

  execute format('select count(*)::bigint from public.clientes c where %s', v_where)
    using p_tenant_id into total_count;

  execute format(
    'select coalesce(jsonb_agg(row_to_json(t)), ''[]''::jsonb) from (
      select c.*,
        count(r.id) as total_equipos,
        sum(case when r.estado = ''entregado'' then 1 else 0 end) as equipos_entregados,
        sum(case when r.estado not in (''entregado'',''inactivo'')
          and r.deleted_at is null then 1 else 0 end) as equipos_activos
      from public.clientes c
      left join public.reparaciones r
        on r.cliente_id = c.id and r.tenant_id = c.tenant_id and r.deleted_at is null
      where %s
      group by c.id
      order by c.id desc
      limit $2 offset $3
    ) t', v_where
  ) using p_tenant_id, p_limit, p_offset into rows;
end;
$$;

-- rpc_cliente_estadisticas: getEstadisticas()
create or replace function public.rpc_cliente_estadisticas(p_tenant_id bigint, p_cliente_id bigint)
returns table(
  total_equipos bigint, completadas bigint, en_proceso bigint, con_garantia bigint
)
language sql stable security definer
set search_path = ''
as $$
  select
    count(*)::bigint as total_equipos,
    sum(case when r.estado = 'entregado' then 1 else 0 end)::bigint as completadas,
    sum(case when r.estado not in ('entregado','inactivo') then 1 else 0 end)::bigint as en_proceso,
    sum(case when g.inicio_garantia_reactivado = true then 1 else 0 end)::bigint as con_garantia
  from public.reparaciones r
  left join public.reparacion_garantias g
    on g.reparacion_id = r.id and g.tenant_id = r.tenant_id
  where r.tenant_id = p_tenant_id and r.cliente_id = p_cliente_id and r.deleted_at is null;
$$;

-- rpc_cliente_equipos: getEquipos()
create or replace function public.rpc_cliente_equipos(p_tenant_id bigint, p_cliente_id bigint)
returns setof public.reparaciones
language sql stable security definer
set search_path = ''
as $$
  select * from public.reparaciones
  where tenant_id = p_tenant_id and cliente_id = p_cliente_id and deleted_at is null
  order by fecha_ingreso desc;
$$;

-- =============================================================================
-- ANALITICAS
-- =============================================================================

-- rpc_kpis_login: getKpisYGraficoLogin()
create or replace function public.rpc_kpis_login(p_tenant_id bigint)
returns jsonb
language plpgsql stable security definer
set search_path = ''
as $$
declare
  v_chart jsonb;
  v_kpi_total bigint;
  v_kpi_en_taller bigint;
  v_kpi_listos bigint;
begin
  select coalesce(jsonb_agg(jsonb_build_object('estado', estado, 'total', total) order by total desc), '[]'::jsonb)
  into v_chart
  from (
    select estado, count(*) as total
    from public.reparaciones
    where tenant_id = p_tenant_id and deleted_at is null
    group by estado
  ) t;

  select count(*)::bigint into v_kpi_total
  from public.reparaciones
  where tenant_id = p_tenant_id and deleted_at is null;

  select count(*)::bigint into v_kpi_en_taller
  from public.reparaciones
  where tenant_id = p_tenant_id and deleted_at is null and estado = 'en_taller';

  select count(*)::bigint into v_kpi_listos
  from public.reparaciones
  where tenant_id = p_tenant_id and deleted_at is null and estado in ('listo');

  return jsonb_build_object(
    'chart_estados', v_chart,
    'kpi_total', v_kpi_total,
    'kpi_en_taller', v_kpi_en_taller,
    'kpi_listos', v_kpi_listos
  );
end;
$$;

-- rpc_inventario_stats: getInventarioStats()
create or replace function public.rpc_inventario_stats(p_tenant_id bigint)
returns jsonb
language sql stable security definer
set search_path = ''
as $$
  select coalesce(jsonb_build_object(
    'valor_total', coalesce(sum(precio_publico * stock), 0),
    'items_totales', coalesce(sum(stock), 0)
  ), '{}'::jsonb)
  from public.inventario
  where tenant_id = p_tenant_id and deleted_at is null;
$$;

-- rpc_top_marcas: findTopMarcas()
create or replace function public.rpc_top_marcas(p_tenant_id bigint, p_limit int default 6)
returns table(equipo_marca text, total bigint)
language sql stable security definer
set search_path = ''
as $$
  select equipo_marca, count(*)::bigint as total
  from public.reparaciones
  where tenant_id = p_tenant_id
    and equipo_marca is not null and equipo_marca != ''
    and deleted_at is null
  group by equipo_marca
  order by total desc
  limit p_limit;
$$;

-- rpc_tendencia_mensual: findTendenciaMensual()
create or replace function public.rpc_tendencia_mensual(p_tenant_id bigint, p_meses int default 6)
returns table(mes text, total bigint)
language sql stable security definer
set search_path = ''
as $$
  select to_char(fecha_ingreso, 'YYYY-MM') as mes, count(*)::bigint as total
  from public.reparaciones
  where tenant_id = p_tenant_id and deleted_at is null
    and fecha_ingreso > now() - (p_meses || ' months')::interval
  group by mes
  order by mes asc;
$$;

-- =============================================================================
-- AUDITORIA
-- =============================================================================

-- rpc_auditoria_reciente: findUltimaAuditoria()
create or replace function public.rpc_auditoria_reciente(p_tenant_id bigint, p_limit int default 100)
returns table(
  id bigint, tenant_id bigint, user_id bigint, accion text, detalle text,
  entidad_id int, entidad_tipo text, created_at timestamptz, updated_at timestamptz,
  cliente text, folio text
)
language sql stable security definer
set search_path = ''
as $$
  select al.*,
    trim(coalesce(c.nombre, '') || ' ' || coalesce(c.apellido, '')) as cliente,
    r.folio_publico as folio
  from public.actividad_logs al
  left join public.reparaciones r
    on r.tenant_id = al.tenant_id and al.entidad_id = r.id and al.entidad_tipo = 'reparacion'
  left join public.clientes c
    on c.id = r.cliente_id and c.tenant_id = r.tenant_id and c.deleted_at is null
  where al.tenant_id = p_tenant_id
  order by al.created_at desc
  limit p_limit;
$$;

-- =============================================================================
-- SOPORTE
-- =============================================================================

-- rpc_conversaciones_api: findConversacionesParaApi()
create or replace function public.rpc_conversaciones_api(p_tenant_id bigint, p_limit int default 50)
returns table(
  id bigint, remote_jid text, nombre_cliente text, telefono text,
  mensaje text, estado text, fecha_pausa timestamptz,
  fecha_reactivacion timestamptz, minutos_transcurridos numeric
)
language sql stable security definer
set search_path = ''
as $$
  select id, remote_jid, nombre_cliente, telefono, mensaje, estado,
    fecha_pausa, fecha_reactivacion,
    extract(epoch from (now() - fecha_pausa)) / 60 as minutos_transcurridos
  from public.bot_conversaciones
  where tenant_id = p_tenant_id and deleted_at is null
  order by
    case when estado = 'pausado' then 0 else 1 end,
    fecha_pausa desc
  limit p_limit;
$$;

-- =============================================================================
-- FUNCIONES ADICIONALES (referenciadas por repositorios)
-- =============================================================================

-- rpc_top_modelos: findTopModelos()
create or replace function public.rpc_top_modelos(p_tenant_id bigint, p_limit int default 5)
returns table(equipo_modelo text, equipo_marca text, total bigint)
language sql stable security definer
set search_path = ''
as $$
  select equipo_modelo, max(equipo_marca) as equipo_marca, count(*)::bigint as total
  from public.reparaciones
  where tenant_id = p_tenant_id and deleted_at is null
  group by equipo_modelo
  order by total desc
  limit p_limit;
$$;

-- rpc_inventario_distribucion_categoria
create or replace function public.rpc_inventario_distribucion_categoria(p_tenant_id bigint)
returns table(categoria text, total_tipos bigint, total_stock bigint)
language sql stable security definer
set search_path = ''
as $$
  select categoria, count(*)::bigint as total_tipos, sum(stock)::bigint as total_stock
  from public.inventario
  where tenant_id = p_tenant_id and deleted_at is null
  group by categoria
  order by total_stock desc;
$$;

-- rpc_inventario_distribucion_subcat
create or replace function public.rpc_inventario_distribucion_subcat(p_tenant_id bigint, p_limit int default 8)
returns table(subcategoria text, total bigint)
language sql stable security definer
set search_path = ''
as $$
  select subcategoria, sum(stock)::bigint as total
  from public.inventario
  where tenant_id = p_tenant_id and deleted_at is null
  group by subcategoria
  order by total desc
  limit p_limit;
$$;

-- rpc_trend_soporte
create or replace function public.rpc_trend_soporte(p_tenant_id bigint, p_dias int default 7)
returns table(dia text, total bigint)
language sql stable security definer
set search_path = ''
as $$
  select to_char(fecha_pausa, 'DD-MM') as dia, count(*)::bigint as total
  from public.bot_conversaciones
  where tenant_id = p_tenant_id and deleted_at is null
    and fecha_pausa > now() - (p_dias || ' days')::interval
  group by dia, fecha_pausa::date
  order by min(fecha_pausa) asc;
$$;

-- rpc_timeline_cliente
create or replace function public.rpc_timeline_cliente(
  p_tenant_id bigint, p_cliente_id bigint, p_offset int, p_limit int,
  out total_count bigint, out rows jsonb
)
language plpgsql stable security definer
set search_path = ''
as $$
begin
  select count(*)::bigint into total_count
  from public.eventos_timeline
  where tenant_id = p_tenant_id and cliente_id = p_cliente_id;

  select coalesce(jsonb_agg(row_to_json(t) order by t.created_at desc), '[]'::jsonb) into rows
  from (
    select et.*, r.folio_publico, r.equipo_marca, r.equipo_modelo
    from public.eventos_timeline et
    left join public.reparaciones r
      on r.tenant_id = et.tenant_id and r.id = et.reparacion_id
    where et.tenant_id = p_tenant_id and et.cliente_id = p_cliente_id
    order by et.created_at desc
    limit p_limit offset p_offset
  ) t;
end;
$$;

-- rpc_timeline_equipo
create or replace function public.rpc_timeline_equipo(
  p_tenant_id bigint, p_reparacion_id bigint, p_offset int, p_limit int,
  out total_count bigint, out rows jsonb
)
language plpgsql stable security definer
set search_path = ''
as $$
begin
  select count(*)::bigint into total_count
  from public.eventos_timeline
  where tenant_id = p_tenant_id and reparacion_id = p_reparacion_id;

  select coalesce(jsonb_agg(row_to_json(t) order by t.created_at desc), '[]'::jsonb) into rows
  from (
    select * from public.eventos_timeline
    where tenant_id = p_tenant_id and reparacion_id = p_reparacion_id
    order by created_at desc
    limit p_limit offset p_offset
  ) t;
end;
$$;

-- rpc_ai_conversaciones: obtenerConversaciones()
create or replace function public.rpc_ai_conversaciones(
  p_tenant_id bigint, p_user_id bigint, p_limit int default 30
)
returns table(
  conversacion_id uuid, titulo text, pregunta text, guardado boolean,
  last_activity timestamptz, created_at timestamptz, msg_count bigint
)
language sql stable security definer
set search_path = ''
as $$
  select conversacion_id,
    min(titulo) as titulo,
    min(pregunta) as pregunta,
    bool_or(guardado) as guardado,
    max(created_at) as last_activity,
    min(created_at) as created_at,
    count(*)::bigint as msg_count
  from public.ai_analisis_historial
  where tenant_id = p_tenant_id and user_id = p_user_id
    and conversacion_id is not null
  group by conversacion_id
  order by last_activity desc
  limit p_limit;
$$;

commit;

-- =============================================================================
-- 0005b: RPCs adicionales para InventarioCategoriaRepository
-- =============================================================================
begin;

-- rpc_servicios_paginado
create or replace function public.rpc_servicios_paginado(
  p_tenant_id bigint, p_offset int, p_limit int,
  out total_count bigint, out rows jsonb
)
language plpgsql stable security definer set search_path = ''
as $$
begin
  select count(*)::bigint into total_count
  from public.inv_servicios_generales
  where tenant_id = p_tenant_id and deleted_at is null;

  select coalesce(jsonb_agg(row_to_json(t) order by t.subcategoria asc, t.created_at desc), '[]'::jsonb) into rows
  from (
    select sg.*,
      coalesce(string_agg(sa.accion, '||' order by sa.orden), '') as acciones_lista
    from public.inv_servicios_generales sg
    left join public.inv_servicios_acciones sa
      on sa.tenant_id = sg.tenant_id and sa.servicio_id = sg.id and sa.deleted_at is null
    where sg.tenant_id = p_tenant_id and sg.deleted_at is null
    group by sg.id
    limit p_limit offset p_offset
  ) t;
end;
$$;

-- rpc_pantallas_paginado
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
    select p.*, pm.nombre as modelo_nombre, pmt.nombre as modelo_tecnico_nombre
    from public.inv_pantallas p
    left join public.pantallas_modelos pm on pm.tenant_id = p.tenant_id and pm.id = p.modelo_id
    left join public.pantallas_modelos_tecnicos pmt on pmt.tenant_id = p.tenant_id and pmt.id = p.modelo_tecnico_id
    where p.tenant_id = p_tenant_id and p.deleted_at is null
    limit p_limit offset p_offset
  ) t;
end;
$$;

-- rpc_accesorios_paginado
create or replace function public.rpc_accesorios_paginado(
  p_tenant_id bigint, p_offset int, p_limit int,
  out total_count bigint, out rows jsonb
)
language plpgsql stable security definer set search_path = ''
as $$
begin
  select count(*)::bigint into total_count
  from public.inv_accesorios
  where tenant_id = p_tenant_id and deleted_at is null;

  select coalesce(jsonb_agg(row_to_json(t) order by t.subcategoria_nombre asc, t.nombre_producto asc), '[]'::jsonb) into rows
  from (
    select a.*,
      asub.nombre as subcategoria_nombre,
      am.nombre as marca_nombre,
      ac.nombre as color_nombre
    from public.inv_accesorios a
    left join public.accesorios_subcategorias asub on asub.tenant_id = a.tenant_id and asub.id = a.subcategoria_id
    left join public.accesorios_marcas am on am.tenant_id = a.tenant_id and am.id = a.marca_id
    left join public.accesorios_colores ac on ac.tenant_id = a.tenant_id and ac.id = a.color_id
    where a.tenant_id = p_tenant_id and a.deleted_at is null
    limit p_limit offset p_offset
  ) t;
end;
$$;

-- rpc_kpis_categoria: KPIs por tipo de categoria
create or replace function public.rpc_kpis_categoria(p_tenant_id bigint, p_categoria text)
returns jsonb
language plpgsql stable security definer set search_path = ''
as $$
declare
  v_result jsonb;
begin
  if p_categoria = 'servicios' then
    select jsonb_agg(jsonb_build_object(
      'label', lbl, 'value', val, 'icon', icn, 'color', clr
    )) into v_result
    from (
      select 'Total Servicios' as lbl,
        count(*)::text as val, 'bi-gear-wide-connected' as icn, 'primary' as clr
      from public.inv_servicios_generales
      where tenant_id = p_tenant_id and deleted_at is null
      union all
      select 'Rango Precios',
        '$' || to_char(coalesce(min(precio), 0), 'FM999,999') || ' — $' || to_char(coalesce(max(precio), 0), 'FM999,999'),
        'bi-cash-stack', 'success'
      from public.inv_servicios_generales
      where tenant_id = p_tenant_id and deleted_at is null
      union all
      select 'Con Garantia',
        (sum(case when garantia = 'SI' then 1 else 0 end))::text,
        'bi-shield-check', 'info'
      from public.inv_servicios_generales
      where tenant_id = p_tenant_id and deleted_at is null
    ) t;
  elsif p_categoria = 'baterias' then
    select jsonb_agg(jsonb_build_object('label', lbl, 'value', val, 'icon', icn, 'color', clr)) into v_result
    from (
      select 'Total Baterias', count(*)::text, 'bi-battery-charging', 'success'
      from public.inv_baterias where tenant_id = p_tenant_id and deleted_at is null
      union all
      select 'Unidades en stock', coalesce(sum(stock), 0)::text, 'bi-box-seam', 'info'
      from public.inv_baterias where tenant_id = p_tenant_id and deleted_at is null
      union all
      select 'Valor en stock',
        '$' || to_char(coalesce(sum(precio * stock), 0), 'FM999,999'),
        'bi-currency-dollar', 'warning'
      from public.inv_baterias where tenant_id = p_tenant_id and deleted_at is null
    ) t;
  elsif p_categoria = 'pantallas' then
    select jsonb_agg(jsonb_build_object('label', lbl, 'value', val, 'icon', icn, 'color', clr)) into v_result
    from (
      select 'Total Pantallas', count(*)::text, 'bi-phone', 'info'
      from public.inv_pantallas where tenant_id = p_tenant_id and deleted_at is null
      union all
      select 'Rango Precios',
        '$' || to_char(coalesce(min(precio), 0), 'FM999,999') || ' — $' || to_char(coalesce(max(precio), 0), 'FM999,999'),
        'bi-cash-stack', 'success'
      from public.inv_pantallas where tenant_id = p_tenant_id and deleted_at is null
      union all
      select 'Modelos', (count(distinct modelo_id))::text, 'bi-collection', 'warning'
      from public.inv_pantallas where tenant_id = p_tenant_id and deleted_at is null
    ) t;
  elsif p_categoria = 'accesorios' then
    select jsonb_agg(jsonb_build_object('label', lbl, 'value', val, 'icon', icn, 'color', clr)) into v_result
    from (
      select 'Total Productos', count(*)::text, 'bi-headphones', 'purple'
      from public.inv_accesorios where tenant_id = p_tenant_id and deleted_at is null
      union all
      select 'Valor en Stock',
        '$' || to_char(coalesce(sum(precio * stock), 0), 'FM999,999'),
        'bi-currency-dollar', 'success'
      from public.inv_accesorios where tenant_id = p_tenant_id and deleted_at is null
      union all
      select 'Stock Bajo',
        (sum(case when stock < 3 then 1 else 0 end))::text,
        'bi-exclamation-triangle', 'warning'
      from public.inv_accesorios where tenant_id = p_tenant_id and deleted_at is null
    ) t;
  else
    v_result := '[]'::jsonb;
  end if;
  return coalesce(v_result, '[]'::jsonb);
end;
$$;

-- rpc_ejecutar_consulta_segura: ejecuta SQL generado por la IA (solo SELECT)
create or replace function public.rpc_ejecutar_consulta_segura(p_sql text)
returns jsonb
language plpgsql security definer set search_path = ''
as $$
declare
  v_rows jsonb;
  v_cols jsonb;
begin
  if not (lower(btrim(p_sql)) like 'select%') then
    return jsonb_build_object('error', 'Solo consultas SELECT permitidas');
  end if;

  p_sql := regexp_replace(p_sql, ';\s*$', '');

  begin
    execute format(
      'with result as (%s) select coalesce(jsonb_agg(row_to_json(r)), ''[]''::jsonb) from result r',
      p_sql
    ) into v_rows;

    execute format(
      'with result as (%s) select coalesce(jsonb_agg(a.key), ''[]''::jsonb) from result r, lateral jsonb_each(to_jsonb(r)) a limit 1',
      p_sql
    ) into v_cols;

    return jsonb_build_object(
      'columns', coalesce(v_cols, '[]'::jsonb),
      'rows', coalesce(v_rows, '[]'::jsonb),
      'row_count', jsonb_array_length(coalesce(v_rows, '[]'::jsonb))
    );
  exception when others then
    return jsonb_build_object('error', SQLERRM);
  end;
end;
$$;

commit;
