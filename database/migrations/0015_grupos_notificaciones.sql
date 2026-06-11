-- 0015: grupos_notificaciones — Agrupación de notificaciones configurables por tenant
begin;

create table if not exists public.grupos_notificaciones (
    id          bigserial primary key,
    tenant_id   bigint not null,
    nombre      text not null,
    orden       int not null default 0,
    activo      boolean not null default true,
    created_at  timestamptz not null default now()
);

create index if not exists idx_grupos_notificaciones_tenant on public.grupos_notificaciones(tenant_id);

-- RLS: lectura por tenant, escritura solo admin
alter table public.grupos_notificaciones enable row level security;

drop policy if exists grupos_notif_select on public.grupos_notificaciones;
create policy grupos_notif_select on public.grupos_notificaciones
    for select using (tenant_id = current_tenant_id());

drop policy if exists grupos_notif_admin on public.grupos_notificaciones;
create policy grupos_notif_admin on public.grupos_notificaciones
    for all using (
        tenant_id = current_tenant_id()
        and auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin'
    ) with check (
        tenant_id = current_tenant_id()
        and auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin'
    );

drop policy if exists grupos_notif_service on public.grupos_notificaciones;
create policy grupos_notif_service on public.grupos_notificaciones
    for all to service_role using (true) with check (true);

-- FK en notificaciones_config
do $$
begin
    if not exists (
        select 1 from information_schema.columns
        where table_name = 'notificaciones_config' and column_name = 'grupo_id'
    ) then
        alter table public.notificaciones_config
            add column grupo_id bigint references public.grupos_notificaciones(id) on delete set null;
    end if;
end;
$$;

commit;
