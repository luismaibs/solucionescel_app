-- =============================================================================
-- 0015_unificar_estados.sql
-- Unifica el sistema de estados: elimina CHECK constraint fijo para aceptar
-- slugs dinamicos desde estados_config.
-- =============================================================================

-- Dropear CHECK constraint del campo reparaciones.estado
-- (constraint inline sin nombre explicito, PostgreSQL auto-genera
--  el nombre como {tabla}_{columna}_check)
alter table public.reparaciones drop constraint if exists reparaciones_estado_check;

-- Si la constraint tiene nombre distinto (ej: de version mas vieja),
-- buscar y dropear dinamicamente:
do $$
declare
    _cn text;
begin
    select conname into _cn
    from pg_constraint
    where conrelid = 'public.reparaciones'::regclass
      and contype = 'c'
      and pg_get_constraintdef(oid, true) like '%estado%';

    if found and _cn is not null then
        execute format('alter table public.reparaciones drop constraint if exists %I', _cn);
    end if;
end $$;
