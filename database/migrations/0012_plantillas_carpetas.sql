-- =============================================================================
-- Migracion 0012: Carpetas para whatsapp_templates + FK
-- =============================================================================
begin;

-- Tabla de carpetas para agrupar plantillas
create table if not exists public.whatsapp_template_carpetas (
  id bigserial primary key,
  nombre text not null,
  tenant_id bigint not null,
  created_at timestamptz not null default now()
);

-- FK de carpeta en whatsapp_templates (nullable = sin carpeta)
do $$ begin
  if not exists (
    select 1 from information_schema.columns
    where table_schema = 'public' and table_name = 'whatsapp_templates' and column_name = 'carpeta_id'
  ) then
    alter table public.whatsapp_templates add column carpeta_id bigint references public.whatsapp_template_carpetas(id) on delete set null;
  end if;
end $$;

-- RLS carpetas
alter table public.whatsapp_template_carpetas enable row level security;

drop policy if exists p_carpetas_read on public.whatsapp_template_carpetas;
create policy p_carpetas_read on public.whatsapp_template_carpetas
  for select to authenticated using (true);

drop policy if exists p_carpetas_admin on public.whatsapp_template_carpetas;
create policy p_carpetas_admin on public.whatsapp_template_carpetas
  for all to authenticated
  using (coalesce(auth.jwt() ->> 'rol', '') = 'admin')
  with check (coalesce(auth.jwt() ->> 'rol', '') = 'admin');

commit;
