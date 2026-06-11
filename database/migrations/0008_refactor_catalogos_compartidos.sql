-- =============================================================================
-- Migración 0008: Catálogos compartidos — marcas, modelos, colores, subcategorias
-- Elimina: accesorios_marcas, accesorios_colores, accesorios_subcategorias,
--          pantallas_modelos, pantallas_modelos_tecnicos, equipos_marcas, inventario (legacy)
-- =============================================================================
begin;

-- =============================================================================
-- 1. Crear tablas catálogo compartidas
-- =============================================================================

create table if not exists public.marcas (
  id         bigserial primary key,
  tenant_id  bigint not null references public.tenants(id) on delete restrict on update cascade,
  nombre     text not null,
  activo     boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (tenant_id, nombre)
);
create index if not exists idx_marcas_tenant on public.marcas(tenant_id);

create table if not exists public.modelos (
  id         bigserial primary key,
  tenant_id  bigint not null references public.tenants(id) on delete restrict on update cascade,
  nombre     text not null,
  activo     boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (tenant_id, nombre)
);
create index if not exists idx_modelos_tenant on public.modelos(tenant_id);

create table if not exists public.colores (
  id         bigserial primary key,
  tenant_id  bigint not null references public.tenants(id) on delete restrict on update cascade,
  nombre     text not null,
  activo     boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (tenant_id, nombre)
);
create index if not exists idx_colores_tenant on public.colores(tenant_id);

create table if not exists public.subcategorias (
  id         bigserial primary key,
  tenant_id  bigint not null references public.tenants(id) on delete restrict on update cascade,
  nombre     text not null,
  activo     boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (tenant_id, nombre)
);
create index if not exists idx_subcategorias_tenant on public.subcategorias(tenant_id);

-- =============================================================================
-- 2. Migrar datos a las nuevas tablas
-- =============================================================================

-- Marcas: accesorios_marcas + equipos_marcas (deduplicar por tenant+nombre)
insert into public.marcas (tenant_id, nombre, activo, created_at, updated_at)
  select tenant_id, nombre, activo, created_at, updated_at from public.accesorios_marcas
  on conflict (tenant_id, nombre) do nothing;

insert into public.marcas (tenant_id, nombre, activo, created_at, updated_at)
  select tenant_id, nombre, true, created_at, updated_at from public.equipos_marcas
  on conflict (tenant_id, nombre) do nothing;

-- Modelos: pantallas_modelos + pantallas_modelos_tecnicos
insert into public.modelos (tenant_id, nombre, activo, created_at, updated_at)
  select tenant_id, nombre, activo, created_at, updated_at from public.pantallas_modelos
  on conflict (tenant_id, nombre) do nothing;

insert into public.modelos (tenant_id, nombre, activo, created_at, updated_at)
  select tenant_id, nombre, activo, created_at, updated_at from public.pantallas_modelos_tecnicos
  on conflict (tenant_id, nombre) do nothing;

-- Colores: copiar accesorios_colores
insert into public.colores (tenant_id, nombre, activo, created_at, updated_at)
  select tenant_id, nombre, activo, created_at, updated_at from public.accesorios_colores
  on conflict (tenant_id, nombre) do nothing;

-- Subcategorias: copiar accesorios_subcategorias
insert into public.subcategorias (tenant_id, nombre, activo, created_at, updated_at)
  select tenant_id, nombre, activo, created_at, updated_at from public.accesorios_subcategorias
  on conflict (tenant_id, nombre) do nothing;

-- =============================================================================
-- 3. Actualizar inv_accesorios — redirigir FKs a tablas nuevas
-- =============================================================================
alter table public.inv_accesorios
  add column if not exists marca_id_new    bigint,
  add column if not exists color_id_new    bigint,
  add column if not exists subcat_id_new   bigint;

update public.inv_accesorios ia
set marca_id_new = m.id
from public.accesorios_marcas am
join public.marcas m on m.tenant_id = am.tenant_id and m.nombre = am.nombre
where ia.marca_id = am.id and ia.tenant_id = am.tenant_id;

update public.inv_accesorios ia
set color_id_new = c.id
from public.accesorios_colores ac
join public.colores c on c.tenant_id = ac.tenant_id and c.nombre = ac.nombre
where ia.color_id = ac.id and ia.tenant_id = ac.tenant_id;

update public.inv_accesorios ia
set subcat_id_new = s.id
from public.accesorios_subcategorias asc2
join public.subcategorias s on s.tenant_id = asc2.tenant_id and s.nombre = asc2.nombre
where ia.subcategoria_id = asc2.id and ia.tenant_id = asc2.tenant_id;

-- Reemplazar columnas viejas con las nuevas
alter table public.inv_accesorios drop column marca_id;
alter table public.inv_accesorios drop column color_id;
alter table public.inv_accesorios drop column subcategoria_id;

alter table public.inv_accesorios rename column marca_id_new    to marca_id;
alter table public.inv_accesorios rename column color_id_new    to color_id;
alter table public.inv_accesorios rename column subcat_id_new   to subcategoria_id;

alter table public.inv_accesorios
  add constraint fk_inv_accesorios_marca     foreign key (marca_id)        references public.marcas(id),
  add constraint fk_inv_accesorios_color     foreign key (color_id)        references public.colores(id),
  add constraint fk_inv_accesorios_subcateg  foreign key (subcategoria_id) references public.subcategorias(id);

-- =============================================================================
-- 4. Actualizar inv_pantallas — redirigir FKs
-- =============================================================================
alter table public.inv_pantallas
  add column if not exists marca_id        bigint,
  add column if not exists modelo_id_new   bigint,
  add column if not exists mod_tec_id_new  bigint;

update public.inv_pantallas ip
set modelo_id_new = m.id
from public.pantallas_modelos pm
join public.modelos m on m.tenant_id = pm.tenant_id and m.nombre = pm.nombre
where ip.modelo_id = pm.id and ip.tenant_id = pm.tenant_id;

update public.inv_pantallas ip
set mod_tec_id_new = m.id
from public.pantallas_modelos_tecnicos pmt
join public.modelos m on m.tenant_id = pmt.tenant_id and m.nombre = pmt.nombre
where ip.modelo_tecnico_id = pmt.id and ip.tenant_id = pmt.tenant_id;

alter table public.inv_pantallas drop column modelo_id;
alter table public.inv_pantallas drop column modelo_tecnico_id;

alter table public.inv_pantallas rename column modelo_id_new  to modelo_id;
alter table public.inv_pantallas rename column mod_tec_id_new to modelo_tecnico_id;

alter table public.inv_pantallas
  add constraint fk_inv_pantallas_modelo      foreign key (modelo_id)         references public.modelos(id),
  add constraint fk_inv_pantallas_mod_tecnico foreign key (modelo_tecnico_id) references public.modelos(id);

-- =============================================================================
-- 5. Actualizar inv_baterias — convertir texto crudo a FKs
-- =============================================================================
alter table public.inv_baterias
  add column if not exists marca_id  bigint,
  add column if not exists modelo_id bigint;

-- Insertar marcas únicas de baterías en la tabla compartida
insert into public.marcas (tenant_id, nombre, activo)
  select distinct tenant_id, marca, true from public.inv_baterias
  where marca is not null and marca <> ''
  on conflict (tenant_id, nombre) do nothing;

-- Insertar modelos únicos de baterías en la tabla compartida
insert into public.modelos (tenant_id, nombre, activo)
  select distinct tenant_id, modelo_bateria, true from public.inv_baterias
  where modelo_bateria is not null and modelo_bateria <> ''
  on conflict (tenant_id, nombre) do nothing;

-- Mapear texto → FK
update public.inv_baterias ib
set marca_id = m.id
from public.marcas m
where m.tenant_id = ib.tenant_id and m.nombre = ib.marca;

update public.inv_baterias ib
set modelo_id = m.id
from public.modelos m
where m.tenant_id = ib.tenant_id and m.nombre = ib.modelo_bateria;

-- Eliminar columnas de texto crudo
alter table public.inv_baterias drop column if exists marca;
alter table public.inv_baterias drop column if exists modelo_bateria;

alter table public.inv_baterias
  add constraint fk_inv_baterias_marca  foreign key (marca_id)  references public.marcas(id),
  add constraint fk_inv_baterias_modelo foreign key (modelo_id) references public.modelos(id);

-- =============================================================================
-- 6. Remalear reparaciones.equipo_marca_id → marcas
--    (la FK reparaciones_equipo_marca_id_fkey apunta a equipos_marcas)
-- =============================================================================
alter table public.reparaciones add column if not exists equipo_marca_id_new bigint;

update public.reparaciones r
set equipo_marca_id_new = m.id
from public.equipos_marcas em
join public.marcas m on m.tenant_id = em.tenant_id and m.nombre = em.nombre
where r.equipo_marca_id = em.id;

alter table public.reparaciones drop constraint if exists reparaciones_equipo_marca_id_fkey;
alter table public.reparaciones drop column if exists equipo_marca_id;
alter table public.reparaciones rename column equipo_marca_id_new to equipo_marca_id;

alter table public.reparaciones
  add constraint fk_reparaciones_marca foreign key (equipo_marca_id) references public.marcas(id) on delete set null on update cascade;

-- =============================================================================
-- 7. Eliminar tablas legacy y catálogos fragmentados
-- =============================================================================
drop table if exists public.inventario;
drop table if exists public.accesorios_marcas;
drop table if exists public.accesorios_colores;
drop table if exists public.accesorios_subcategorias;
drop table if exists public.pantallas_modelos;
drop table if exists public.pantallas_modelos_tecnicos;
drop table if exists public.equipos_marcas;

commit;
