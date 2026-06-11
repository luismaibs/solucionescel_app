-- =============================================================================
-- Migracion 0003: Datos semilla (estados, roles, permisos)
-- Ejecutar despues de 0001 y 0002
-- =============================================================================
begin;

-- Estados de reparacion (globales)
insert into public.reparacion_estados (slug, nombre, orden)
values
  ('en_taller',            'Laboratorio',                    0),
  ('listo',                'Listo',                          1),
  ('no_quedo',             'No Quedo',                       2),
  ('entregado',            'Entregado',                      3),
  ('garantia_activada',    'Proceso de revision tecnica',    4),
  ('garantia_finalizada',  'Garantia exitosa',               5),
  ('garantia_fallida',     'Garantia fallida',               6),
  ('garantia_entregada',   'Garantia entregada',             7),
  ('inactivo',             'Inactivo',                       8)
on conflict (slug) do update set
  nombre = excluded.nombre,
  orden = excluded.orden;

-- Los roles y permisos ya vienen de 0001. Confirmar con do nothing.

commit;
