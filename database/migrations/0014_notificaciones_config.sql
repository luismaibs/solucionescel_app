-- 0014: notificaciones_config — Notificaciones configurables por tenant
-- Similar a estados_config, permite crear notificaciones con slug único
begin;

create table if not exists public.notificaciones_config (
    id              uuid default gen_random_uuid() primary key,
    tenant_id       bigint not null,
    slug            text not null,
    titulo          text not null,
    mensaje         text not null default '',
    tipo            varchar(20) not null default 'info'
                    check (tipo in ('info','warning','error','success')),
    icono           varchar(50) not null default 'bell-fill',
    plantilla_id    bigint,
    activo          boolean not null default true,
    orden           int not null default 0,
    created_at      timestamptz not null default now(),
    updated_at      timestamptz not null default now(),
    unique(tenant_id, slug)
);

create index if not exists idx_notificaciones_config_tenant on public.notificaciones_config(tenant_id);

-- FK a whatsapp_templates
do $$
begin
    if exists (select 1 from information_schema.tables where table_name = 'whatsapp_templates') then
        execute 'alter table public.notificaciones_config add constraint fk_notificaciones_plantilla
                 foreign key (plantilla_id) references public.whatsapp_templates(id) on delete set null';
    end if;
end;
$$;

-- Trigger updated_at
do $$
begin
    if not exists (select 1 from pg_trigger where tgname = 'set_notificaciones_config_updated_at') then
        create trigger set_notificaciones_config_updated_at
            before update on public.notificaciones_config
            for each row execute function public.trigger_set_updated_at();
    end if;
end;
$$;

-- RLS: por tenant, admin escribe
alter table public.notificaciones_config enable row level security;

drop policy if exists notif_config_select on public.notificaciones_config;
create policy notif_config_select on public.notificaciones_config
    for select using (tenant_id = current_tenant_id());

drop policy if exists notif_config_admin on public.notificaciones_config;
create policy notif_config_admin on public.notificaciones_config
    for all using (
        tenant_id = current_tenant_id()
        and auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin'
    ) with check (
        tenant_id = current_tenant_id()
        and auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin'
    );

drop policy if exists notif_config_service on public.notificaciones_config;
create policy notif_config_service on public.notificaciones_config
    for all to service_role using (true) with check (true);

commit;
