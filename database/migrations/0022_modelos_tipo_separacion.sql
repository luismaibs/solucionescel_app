-- =============================================================================
-- Migracion 0022: Separar modelos de modelos tecnicos en tabla compartida
-- Agrega columna tipo para aislar cada catalogo en la misma tabla
-- =============================================================================
begin;

-- Agregar columna tipo con valor por defecto 'modelo'
alter table public.modelos
  add column if not exists tipo text not null default 'modelo';

alter table public.modelos
  add constraint chk_modelos_tipo check (tipo in ('modelo', 'modelo_tecnico'));

-- La restriccion unica existente (tenant_id, nombre) impediria el mismo nombre
-- en ambos tipos. Cambiarla a (tenant_id, nombre, tipo) para permitirlo.
alter table public.modelos
  drop constraint if exists modelos_tenant_id_nombre_key;

alter table public.modelos
  add constraint modelos_tenant_nombre_tipo_key unique (tenant_id, nombre, tipo);

commit;
