-- 0010: estados_config — Sistema dinámico de estados por tenant
-- Reemplaza progresivamente a reparacion_estados (legacy global)
begin;

create table if not exists public.estados_config (
    id              uuid default gen_random_uuid() primary key,
    tenant_id       bigint not null,
    parent_id       uuid references public.estados_config(id) on delete cascade,
    slug            text not null,
    nombre          text not null,
    descripcion     text default '',
    color           varchar(7) not null default '#94a3b8',
    tipo            varchar(20) not null check (tipo in ('primer_ingreso','re_ingreso')),
    habilitar_reingreso boolean not null default false,
    plantilla_id    bigint,  -- FK a whatsapp_templates se agrega abajo si la tabla existe
    orden           int not null default 0,
    activo          boolean not null default true,
    created_at      timestamptz not null default now(),
    updated_at      timestamptz not null default now(),
    unique(tenant_id, slug)
);

alter table public.estados_config add constraint chk_estados_reingreso
    check (habilitar_reingreso = false or (parent_id is null and tipo = 'primer_ingreso'));

create index if not exists idx_estados_config_tenant on public.estados_config(tenant_id);
create index if not exists idx_estados_config_parent on public.estados_config(parent_id);

-- FK a whatsapp_templates (puede no existir aún)
do $$
begin
    if exists (select 1 from information_schema.tables where table_name = 'whatsapp_templates') then
        execute 'alter table public.estados_config add constraint fk_estados_plantilla
                 foreign key (plantilla_id) references public.whatsapp_templates(id) on delete set null';
    end if;
end;
$$;

-- Trigger updated_at
do $$
begin
    if not exists (select 1 from pg_trigger where tgname = 'set_estados_config_updated_at') then
        create trigger set_estados_config_updated_at
            before update on public.estados_config
            for each row execute function public.trigger_set_updated_at();
    end if;
end;
$$;

-- RLS: por tenant, admin escribe
alter table public.estados_config enable row level security;

drop policy if exists estados_select on public.estados_config;
create policy estados_select on public.estados_config
    for select using (tenant_id = current_tenant_id());

drop policy if exists estados_admin on public.estados_config;
create policy estados_admin on public.estados_config
    for all using (
        tenant_id = current_tenant_id()
        and auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin'
    ) with check (
        tenant_id = current_tenant_id()
        and auth.jwt() -> 'app_metadata' ->> 'rol' = 'admin'
    );

drop policy if exists estados_service on public.estados_config;
create policy estados_service on public.estados_config
    for all to service_role using (true) with check (true);

-- RPC: inicializar estados por defecto para un tenant
create or replace function public.rpc_initialize_estados_default(p_tenant_id bigint)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    v_listo_id uuid;
    v_count    int;
begin
    select count(*) into v_count from public.estados_config where tenant_id = p_tenant_id;
    if v_count > 0 then
        return jsonb_build_object('ok', true, 'message', 'Ya existen estados configurados', 'count', v_count);
    end if;

    -- Primer Ingreso
    insert into estados_config (tenant_id, slug, nombre, descripcion, color, tipo, habilitar_reingreso, orden) values
    (p_tenant_id, 'status_laboratorio', 'Laboratorio', 'Equipo en proceso de reparación',                       '#3b82f6', 'primer_ingreso', false, 0),
    (p_tenant_id, 'status_listo',       'Listo',       'Reparación exitosa, listo para entrega',                 '#22c55e', 'primer_ingreso', true,  1),
    (p_tenant_id, 'status_no_quedo',    'No Quedó',    'No se pudo reparar',                                    '#f43f5e', 'primer_ingreso', true,  2),
    (p_tenant_id, 'status_entregado',   'Entregado',   'Equipo entregado al cliente',                            '#64748b', 'primer_ingreso', false, 3);

    select id into v_listo_id from estados_config where tenant_id = p_tenant_id and slug = 'status_listo';

    -- Subestados de Listo
    insert into estados_config (tenant_id, parent_id, slug, nombre, color, tipo, orden) values
    (p_tenant_id, v_listo_id, 'sub_listo_garantia_prov_30', 'Garantía técnica y proveedor 30 días', '#22c55e', 'primer_ingreso', 0),
    (p_tenant_id, v_listo_id, 'sub_listo_garantia_30',      'Garantía técnica 30 días',            '#22c55e', 'primer_ingreso', 1),
    (p_tenant_id, v_listo_id, 'sub_listo_garantia_60',      'Garantía técnica 60 días',            '#22c55e', 'primer_ingreso', 2),
    (p_tenant_id, v_listo_id, 'sub_listo_garantia_90',      'Garantía técnica 90 días',            '#22c55e', 'primer_ingreso', 3),
    (p_tenant_id, v_listo_id, 'sub_listo_sin_garantia',     'Sin garantía',                        '#22c55e', 'primer_ingreso', 4);

    -- Re Ingreso
    insert into estados_config (tenant_id, slug, nombre, descripcion, color, tipo, orden) values
    (p_tenant_id, 'status_garantia_exitosa',   'Garantía Exitosa',   'Garantía resuelta satisfactoriamente', '#a855f7', 're_ingreso', 0),
    (p_tenant_id, 'status_garantia_fallida',   'Garantía Fallida',   'Garantía no resuelta',                '#dc2626', 're_ingreso', 1),
    (p_tenant_id, 'status_garantia_entregada', 'Garantía Entregada', 'Equipo en garantía entregado',        '#334155', 're_ingreso', 2);

    return jsonb_build_object('ok', true, 'message', 'Estados inicializados correctamente', 'count', 12);
end;
$$;

commit;
