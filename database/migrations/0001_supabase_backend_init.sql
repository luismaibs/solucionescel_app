-- Migracion base para Supabase/PostgreSQL (self-hosted)
-- Crea tablas base de backend multi-tenant y politicas RLS iniciales.

begin;

create extension if not exists pgcrypto;

create table if not exists public.tenants (
  id bigserial primary key,
  nombre text not null,
  slug text not null unique,
  activo boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.roles (
  id bigserial primary key,
  nombre text not null,
  slug text not null unique,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.permisos (
  id bigserial primary key,
  slug text not null unique,
  nombre text not null,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.role_permiso (
  role_id bigint not null references public.roles(id) on delete cascade,
  permiso_id bigint not null references public.permisos(id) on delete cascade,
  primary key (role_id, permiso_id)
);

create table if not exists public.usuarios (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict,
  username text not null,
  password_hash text not null,
  nombre_completo text not null,
  rol text not null default 'usuario',
  activo boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  created_by_user_id bigint null references public.usuarios(id) on delete set null,
  deleted_at timestamptz null,
  unique (tenant_id, username)
);

create table if not exists public.ai_analisis_historial (
  id bigserial primary key,
  tenant_id bigint not null references public.tenants(id) on delete restrict,
  user_id bigint null references public.usuarios(id) on delete set null,
  conversacion_id uuid not null default gen_random_uuid(),
  titulo text null,
  pregunta text not null,
  respuesta text null,
  sql_generado text null,
  ruta_datos_resultado text null,
  ruta_visualizacion text null,
  guardado boolean not null default false,
  created_at timestamptz not null default now()
);

create index if not exists idx_usuarios_tenant_rol on public.usuarios(tenant_id, rol);
create index if not exists idx_usuarios_deleted_at on public.usuarios(deleted_at);
create index if not exists idx_ai_historial_tenant_user_created on public.ai_analisis_historial(tenant_id, user_id, created_at desc);
create index if not exists idx_ai_historial_conv on public.ai_analisis_historial(conversacion_id);

insert into public.roles (nombre, slug)
values ('Administrador', 'admin'), ('Usuario', 'usuario')
on conflict (slug) do nothing;

insert into public.permisos (slug, nombre)
values
  ('admin', 'Acceso total (administrador)'),
  ('ver_reparaciones', 'Ver equipos/reparaciones'),
  ('editar_reparaciones', 'Editar equipos/reparaciones'),
  ('ver_usuarios', 'Ver usuarios'),
  ('editar_usuarios', 'Editar usuarios'),
  ('ver_analiticas', 'Ver analiticas'),
  ('ver_inventario', 'Ver inventario'),
  ('editar_inventario', 'Editar inventario'),
  ('ver_soporte', 'Ver soporte')
on conflict (slug) do nothing;

insert into public.role_permiso (role_id, permiso_id)
select r.id, p.id
from public.roles r
cross join public.permisos p
where r.slug = 'admin'
on conflict do nothing;

alter table public.tenants enable row level security;
alter table public.usuarios enable row level security;
alter table public.ai_analisis_historial enable row level security;

drop policy if exists p_tenants_read on public.tenants;
create policy p_tenants_read on public.tenants
for select
to authenticated
using (true);

drop policy if exists p_usuarios_tenant_isolation on public.usuarios;
create policy p_usuarios_tenant_isolation on public.usuarios
for all
to authenticated
using (tenant_id::text = coalesce(auth.jwt() ->> 'tenant_id', ''))
with check (tenant_id::text = coalesce(auth.jwt() ->> 'tenant_id', ''));

drop policy if exists p_ai_historial_tenant_isolation on public.ai_analisis_historial;
create policy p_ai_historial_tenant_isolation on public.ai_analisis_historial
for all
to authenticated
using (tenant_id::text = coalesce(auth.jwt() ->> 'tenant_id', ''))
with check (tenant_id::text = coalesce(auth.jwt() ->> 'tenant_id', ''));

commit;
