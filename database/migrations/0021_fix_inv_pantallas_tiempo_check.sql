-- =============================================================================
-- Migracion 0021: Alinear inv_pantallas.calidad/tiempo con codigos actuales
-- =============================================================================
begin;

do $$
declare
  constraint_name text;
begin
  for constraint_name in
    select con.conname
    from pg_constraint con
    join pg_class rel on rel.oid = con.conrelid
    join pg_namespace nsp on nsp.oid = rel.relnamespace
    where nsp.nspname = 'public'
      and rel.relname = 'inv_pantallas'
      and con.contype = 'c'
      and (
        pg_get_constraintdef(con.oid) ilike '%tiempo%'
        or pg_get_constraintdef(con.oid) ilike '%calidad%'
      )
  loop
    execute format('alter table public.inv_pantallas drop constraint if exists %I', constraint_name);
  end loop;
end;
$$;

alter table public.inv_pantallas
  add constraint inv_pantallas_calidad_check
  check (
    calidad in (
      'C1', 'C2', 'C3',
      'Generico', 'Intermedio', 'Original'
    )
  );

alter table public.inv_pantallas
  add constraint inv_pantallas_tiempo_check
  check (
    tiempo in (
      'TAD 1', 'TAD 2', 'TAD 3', 'TAD 4',
      'TAP 1', 'TAP 2', 'TAP 3', 'TAP 4',
      'OTR 1', 'OTR 2', 'OTR 3', 'OTR 4',
      'Instalacion inmediata 4hrs',
      '2-3 dias full',
      '3-5 dias estandar',
      'Envio internacional 20-30 dias'
    )
  );

commit;
