-- =============================================================================
-- Migracion 0002: Esquema PostgreSQL completo (24 tablas adicionales)
-- Complementa 0001 que ya tiene: tenants, roles, permisos, role_permiso,
-- usuarios, ai_analisis_historial
-- =============================================================================
begin;

-- =============================================================================
-- 0. Extensiones
-- =============================================================================
create extension if not exists pgcrypto;

-- =============================================================================
-- 0.1 Ajustes a tablas de 0001
-- =============================================================================

-- Agregar updated_at a ai_analisis_historial si no existe
do $$
begin
  if not exists (select 1 from information_schema.columns
    where table_schema = 'public' and table_name = 'ai_analisis_historial' and column_name = 'updated_at') then
    alter table public.ai_analisis_historial add column updated_at timestamptz not null default now();
  end if;
end $$;

-- Agregar FK auth.users.id para Supabase Auth
do $$
begin
  if not exists (select 1 from information_schema.columns
    where table_schema = 'public' and table_name = 'usuarios' and column_name = 'auth_user_id') then
    alter table public.usuarios add column auth_user_id uuid unique;
  end if;
end $$;

-- =============================================================================
-- 1. reparacion_estados (global, sin tenant_id)
-- =============================================================================
create table if not exists public.reparacion_estados (
  id bigserial primary key,
  slug text not null unique,
  nombre text not null,
  orden int not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- =============================================================================
-- 2. Catalogos de accesorios (por tenant)
-- =============================================================================
create table if not exists public.accesorios_colores (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  nombre text not null,
  activo boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (tenant_id, nombre)
);
create index if not exists idx_accesorios_colores_tenant on public.accesorios_colores(tenant_id);

create table if not exists public.accesorios_marcas (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  nombre text not null,
  activo boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (tenant_id, nombre)
);
create index if not exists idx_accesorios_marcas_tenant on public.accesorios_marcas(tenant_id);

create table if not exists public.accesorios_subcategorias (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  nombre text not null,
  activo boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (tenant_id, nombre)
);
create index if not exists idx_accesorios_subcategorias_tenant on public.accesorios_subcategorias(tenant_id);

-- =============================================================================
-- 3. Catalogos de pantallas (por tenant)
-- =============================================================================
create table if not exists public.pantallas_modelos (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  nombre text not null,
  activo boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (tenant_id, nombre)
);
create index if not exists idx_pantallas_modelos_tenant on public.pantallas_modelos(tenant_id);

create table if not exists public.pantallas_modelos_tecnicos (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  nombre text not null,
  activo boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (tenant_id, nombre)
);
create index if not exists idx_pantallas_modelos_tecnicos_tenant on public.pantallas_modelos_tecnicos(tenant_id);

-- =============================================================================
-- 4. Clientes (por tenant)
-- =============================================================================
create table if not exists public.clientes (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  nombre text not null,
  apellido text not null,
  telefono text not null,
  correo text null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  created_by_user_id bigint null references public.usuarios(id) on delete set null on update cascade,
  deleted_at timestamptz null,
  unique (tenant_id, telefono)
);
create index if not exists idx_clientes_tenant on public.clientes(tenant_id);
create index if not exists idx_clientes_tenant_deleted on public.clientes(tenant_id, deleted_at);
create index if not exists idx_clientes_tenant_nombre_apellido on public.clientes(tenant_id, nombre, apellido);
create index if not exists idx_clientes_created_by on public.clientes(created_by_user_id);

-- =============================================================================
-- 5. equipos_marcas (por tenant)
-- =============================================================================
create table if not exists public.equipos_marcas (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  nombre text not null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (tenant_id, nombre)
);
create index if not exists idx_equipos_marcas_tenant on public.equipos_marcas(tenant_id);

-- =============================================================================
-- 6. Reparaciones (por tenant) — tabla CORE del negocio
-- =============================================================================
create table if not exists public.reparaciones (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  cliente_id bigint not null references public.clientes(id) on delete restrict on update cascade,
  folio_publico text not null,
  equipo_marca text null,
  equipo_marca_id bigint null references public.equipos_marcas(id) on delete set null on update cascade,
  equipo_modelo text not null,
  falla_reportada text not null,
  estado text not null default 'en_taller'
    check (estado in ('en_taller','listo','no_quedo','entregado',
                      'garantia_activada','garantia_finalizada','garantia_fallida',
                      'garantia_entregada','inactivo')),
  fecha_ingreso timestamptz null,
  created_by_user_id bigint null references public.usuarios(id) on delete set null on update cascade,
  costo_final numeric(10,2) null,
  deleted_at timestamptz null,
  fecha_listo timestamptz null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (tenant_id, folio_publico)
);
create index if not exists idx_reparaciones_tenant on public.reparaciones(tenant_id);
create index if not exists idx_reparaciones_tenant_estado on public.reparaciones(tenant_id, estado);
create index if not exists idx_reparaciones_tenant_estado_fecha on public.reparaciones(tenant_id, estado, fecha_ingreso);
create index if not exists idx_reparaciones_tenant_deleted on public.reparaciones(tenant_id, deleted_at);
create index if not exists idx_reparaciones_cliente on public.reparaciones(cliente_id);
create index if not exists idx_reparaciones_equipo_marca_id on public.reparaciones(equipo_marca_id);

-- =============================================================================
-- 7. Garantias y Mes Azul (por tenant)
-- =============================================================================
create table if not exists public.reparacion_garantias (
  id bigserial primary key,
  reparacion_id bigint not null references public.reparaciones(id) on delete cascade on update cascade,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  tipo_garantia text null
    check (tipo_garantia is null or tipo_garantia in (
      'garantia_tecnica_proveedor_30','garantia_30_dias','garantia_60_dias',
      'garantia_90_dias','sin_garantia')),
  inicio_garantia_reactivado boolean not null default false,
  mes_azul_estado text not null default 'no_aplica'
    check (mes_azul_estado in ('no_aplica','pendiente_inicio','esperando_final','inactivado')),
  mes_azul_inicio_enviado timestamptz null,
  mes_azul_final_enviado timestamptz null,
  mes_azul_fecha_inactivacion timestamptz null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (reparacion_id)
);
create index if not exists idx_reparacion_garantias_tenant on public.reparacion_garantias(tenant_id);

-- =============================================================================
-- 8. Eventos timeline (por tenant)
-- =============================================================================
create table if not exists public.eventos_timeline (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  cliente_id bigint null references public.clientes(id) on delete set null on update cascade,
  reparacion_id bigint null references public.reparaciones(id) on delete set null on update cascade,
  tipo text not null default 'otro'
    check (tipo in ('equipo_ingresado','cambio_estado','mensaje_enviado',
                    'garantia_activada','garantia_reactivada','equipo_entregado',
                    'cliente_creado','cliente_editado','equipo_editado','otro')),
  titulo text not null,
  descripcion text null,
  metadata jsonb null,
  user_id bigint null references public.usuarios(id) on delete set null on update cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index if not exists idx_eventos_timeline_tenant on public.eventos_timeline(tenant_id);
create index if not exists idx_eventos_timeline_cliente on public.eventos_timeline(cliente_id);
create index if not exists idx_eventos_timeline_reparacion on public.eventos_timeline(reparacion_id);
create index if not exists idx_eventos_timeline_tipo on public.eventos_timeline(tipo);
create index if not exists idx_eventos_timeline_created on public.eventos_timeline(created_at);

-- =============================================================================
-- 9. Historial de mensajes (por tenant)
-- =============================================================================
create table if not exists public.historial_mensajes (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  reparacion_id bigint not null references public.reparaciones(id) on delete cascade on update cascade,
  tipo_mensaje text not null,
  contenido_mensaje text null,
  user_id bigint null references public.usuarios(id) on delete set null on update cascade,
  estado_envio text not null default 'pendiente'
    check (estado_envio in ('enviado','fallido','pendiente')),
  respuesta_api text null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index if not exists idx_historial_mensajes_tenant on public.historial_mensajes(tenant_id);
create index if not exists idx_historial_mensajes_reparacion on public.historial_mensajes(reparacion_id);

-- =============================================================================
-- 10. Inventario legacy (por tenant) — DEPRECATED
-- =============================================================================
create table if not exists public.inventario (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  categoria text not null
    check (categoria in ('Accesorios','Reparaciones','Pantallas','Baterias')),
  subcategoria text not null,
  dispositivo_compatible text not null default 'Universal',
  nombre_producto text not null,
  descripcion text null,
  precio_publico numeric(10,2) not null,
  stock int not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  deleted_at timestamptz null
);
create index if not exists idx_inventario_tenant on public.inventario(tenant_id);
create index if not exists idx_inventario_tenant_deleted on public.inventario(tenant_id, deleted_at);
create index if not exists idx_inventario_busqueda on public.inventario(nombre_producto, subcategoria, dispositivo_compatible);

-- =============================================================================
-- 11. Servicios generales (por tenant)
-- =============================================================================
create table if not exists public.inv_servicios_generales (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  subcategoria text not null
    check (subcategoria in ('desbloqueo','liberaciones','servicios','reparaciones','software')),
  gama text not null default 'todas las gamas'
    check (gama in ('baja','media','alta','premium','s.premium','todas las gamas')),
  sistemas_operativos text not null,
  garantia text not null default 'NO'
    check (garantia in ('SI','NO')),
  tiempo_entrega text null,
  precio numeric(10,2) not null,
  nota text null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  deleted_at timestamptz null
);
create index if not exists idx_inv_servicios_tenant on public.inv_servicios_generales(tenant_id);
create index if not exists idx_inv_servicios_tenant_deleted on public.inv_servicios_generales(tenant_id, deleted_at);
create index if not exists idx_inv_servicios_subcat on public.inv_servicios_generales(subcategoria);
create index if not exists idx_inv_servicios_gama on public.inv_servicios_generales(gama);

create table if not exists public.inv_servicios_acciones (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  servicio_id bigint not null references public.inv_servicios_generales(id) on delete cascade on update cascade,
  accion text not null,
  orden int not null default 0,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  deleted_at timestamptz null
);
create index if not exists idx_inv_servicios_acciones_tenant on public.inv_servicios_acciones(tenant_id);
create index if not exists idx_inv_servicios_acciones_tenant_deleted on public.inv_servicios_acciones(tenant_id, deleted_at);
create index if not exists idx_inv_servicios_acciones_svc on public.inv_servicios_acciones(servicio_id);

-- =============================================================================
-- 12. Inv accesorios (por tenant)
-- =============================================================================
create table if not exists public.inv_accesorios (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  subcategoria_id bigint not null references public.accesorios_subcategorias(id) on update cascade,
  marca_id bigint not null references public.accesorios_marcas(id) on update cascade,
  codigo text not null,
  nombre_producto text not null,
  stock int not null default 0,
  precio numeric(10,2) not null,
  color_id bigint not null references public.accesorios_colores(id) on update cascade,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  deleted_at timestamptz null
);
create index if not exists idx_inv_accesorios_tenant on public.inv_accesorios(tenant_id);
create index if not exists idx_inv_accesorios_tenant_deleted on public.inv_accesorios(tenant_id, deleted_at);
create index if not exists idx_inv_accesorios_subcat on public.inv_accesorios(subcategoria_id);
create index if not exists idx_inv_accesorios_marca on public.inv_accesorios(marca_id);
create index if not exists idx_inv_accesorios_color on public.inv_accesorios(color_id);

-- =============================================================================
-- 13. Inv baterias (por tenant)
-- =============================================================================
create table if not exists public.inv_baterias (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  marca text not null,
  calidad text not null,
  tipo text not null,
  modelo_bateria text not null,
  tiempo text not null,
  notas text null,
  precio numeric(10,2) not null default 0.00,
  stock int not null default 0,
  codigo text null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  deleted_at timestamptz null
);
create index if not exists idx_inv_baterias_tenant on public.inv_baterias(tenant_id);
create index if not exists idx_inv_baterias_tenant_deleted on public.inv_baterias(tenant_id, deleted_at);
create index if not exists idx_inv_baterias_marca on public.inv_baterias(marca);

-- =============================================================================
-- 14. Inv pantallas (por tenant)
-- =============================================================================
create table if not exists public.inv_pantallas (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  modelo_id bigint not null references public.pantallas_modelos(id) on update cascade,
  modelo_tecnico_id bigint not null references public.pantallas_modelos_tecnicos(id) on update cascade,
  calidad text not null
    check (calidad in ('Original','Intermedio','Generico')),
  precio numeric(10,2) not null,
  tiempo text not null
    check (tiempo in ('Instalacion inmediata 4hrs','2-3 dias full',
                      '3-5 dias estandar','Envio internacional 20-30 dias')),
  nota text null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  deleted_at timestamptz null
);
create index if not exists idx_inv_pantallas_tenant on public.inv_pantallas(tenant_id);
create index if not exists idx_inv_pantallas_tenant_deleted on public.inv_pantallas(tenant_id, deleted_at);
create index if not exists idx_inv_pantallas_modelo on public.inv_pantallas(modelo_id);
create index if not exists idx_inv_pantallas_modelo_tecnico on public.inv_pantallas(modelo_tecnico_id);
create index if not exists idx_inv_pantallas_calidad on public.inv_pantallas(calidad);

-- =============================================================================
-- 15. Bot conversaciones (por tenant)
-- =============================================================================
create table if not exists public.bot_conversaciones (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  remote_jid text not null,
  nombre_cliente text not null,
  telefono text not null,
  mensaje text null,
  estado text default 'pausado'
    check (estado in ('pausado','activo')),
  fecha_pausa timestamptz null,
  fecha_reactivacion timestamptz null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  deleted_at timestamptz null
);
create index if not exists idx_bot_conversaciones_tenant on public.bot_conversaciones(tenant_id);
create index if not exists idx_bot_conversaciones_tenant_deleted on public.bot_conversaciones(tenant_id, deleted_at);
create index if not exists idx_bot_conversaciones_jid on public.bot_conversaciones(remote_jid);
create index if not exists idx_bot_conversaciones_estado on public.bot_conversaciones(estado);

create table if not exists public.bot_mensajes (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  conversacion_id bigint not null references public.bot_conversaciones(id) on delete cascade on update cascade,
  contenido text null,
  direccion text not null default 'in'
    check (direccion in ('in','out')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index if not exists idx_bot_mensajes_tenant on public.bot_mensajes(tenant_id);
create index if not exists idx_bot_mensajes_tenant_conv on public.bot_mensajes(tenant_id, conversacion_id);
create index if not exists idx_bot_mensajes_created on public.bot_mensajes(created_at);

-- =============================================================================
-- 16. Notificaciones del sistema (por tenant)
-- =============================================================================
create table if not exists public.notificaciones_sistema (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  titulo text not null,
  mensaje text not null,
  tipo text default 'info',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  expira_at timestamptz null
);
create index if not exists idx_notificaciones_tenant on public.notificaciones_sistema(tenant_id);

-- =============================================================================
-- 17. Configuracion de mensajes (por tenant)
-- =============================================================================
create table if not exists public.configuracion_mensajes (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  clave text null,
  nombre text null,
  plantilla text null,
  variables_info text null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (tenant_id, clave)
);
create index if not exists idx_config_mensajes_tenant on public.configuracion_mensajes(tenant_id);

-- =============================================================================
-- 18. Logs y auditoria (por tenant)
-- =============================================================================
create table if not exists public.actividad_logs (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  user_id bigint null references public.usuarios(id) on delete set null on update cascade,
  accion text not null,
  detalle text null,
  entidad_id int null,
  entidad_tipo text null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index if not exists idx_actividad_logs_tenant on public.actividad_logs(tenant_id);
create index if not exists idx_actividad_logs_user on public.actividad_logs(user_id);
create index if not exists idx_actividad_logs_accion on public.actividad_logs(accion);
create index if not exists idx_actividad_logs_created on public.actividad_logs(created_at);

create table if not exists public.sesiones_log (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict on update cascade,
  user_id bigint null references public.usuarios(id) on delete set null on update cascade,
  tipo_usuario text not null
    check (tipo_usuario in ('admin','usuario')),
  accion text not null
    check (accion in ('login_exitoso','login_fallido','logout')),
  ip_address text null,
  user_agent text null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);
create index if not exists idx_sesiones_log_tenant_created on public.sesiones_log(tenant_id, created_at);
create index if not exists idx_sesiones_log_user on public.sesiones_log(user_id);
create index if not exists idx_sesiones_log_created on public.sesiones_log(created_at);
create index if not exists idx_sesiones_log_tipo on public.sesiones_log(tipo_usuario);

-- =============================================================================
-- 19. Trigger: validar tenant_id de created_by_user_id en usuarios
-- =============================================================================
create or replace function public.check_usuario_mismo_tenant()
returns trigger as $$
begin
  if new.created_by_user_id is not null then
    if (select tenant_id from public.usuarios where id = new.created_by_user_id limit 1) != new.tenant_id then
      raise exception 'usuarios.created_by_user_id debe ser del mismo tenant';
    end if;
  end if;
  return new;
end;
$$ language plpgsql;

drop trigger if exists trg_usuarios_tenant_check on public.usuarios;
create trigger trg_usuarios_tenant_check
  before insert or update on public.usuarios
  for each row execute function public.check_usuario_mismo_tenant();

-- =============================================================================
-- 20. Funcion helper: actualizar updated_at automaticamente
-- =============================================================================
create or replace function public.trigger_set_updated_at()
returns trigger as $$
begin
  new.updated_at = now();
  return new;
end;
$$ language plpgsql;

-- Aplicar trigger de updated_at a todas las tablas con esa columna
do $$
declare
  tbl text;
begin
  for tbl in
    select t.table_name from information_schema.tables t
    join information_schema.columns c on c.table_schema = t.table_schema
      and c.table_name = t.table_name and c.column_name = 'updated_at'
    where t.table_schema = 'public' and t.table_type = 'BASE TABLE'
  loop
    execute format(
      'drop trigger if exists trg_%I_updated_at on public.%I;
       create trigger trg_%I_updated_at
         before update on public.%I
         for each row execute function public.trigger_set_updated_at()',
      tbl, tbl, tbl, tbl
    );
  end loop;
end $$;

commit;
