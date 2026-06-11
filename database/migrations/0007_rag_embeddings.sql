-- =============================================================================
-- Migracion 0007: RAG — Busqueda semantica de inventario (pgvector + embeddings)
-- =============================================================================
begin;

-- 1. EXTENSION pgvector
create extension if not exists vector;

-- 2. TABLA inv_embeddings
create table if not exists public.inv_embeddings (
    id bigint generated always as identity primary key,
    tenant_id bigint not null,
    categoria text not null check (categoria in ('accesorios','baterias','pantallas','servicios')),
    producto_id bigint not null,
    texto_busqueda text not null,
    embedding vector(1536),
    created_at timestamptz default now(),
    updated_at timestamptz default now()
);

-- 3. INDICES
create index if not exists idx_inv_embeddings_tenant
    on public.inv_embeddings(tenant_id);

create unique index if not exists idx_inv_embeddings_producto
    on public.inv_embeddings(tenant_id, categoria, producto_id);

-- 4. RLS
alter table public.inv_embeddings enable row level security;

create policy "inv_embeddings_select" on public.inv_embeddings
    for select using (tenant_id = (current_setting('app.tenant_id', true)::bigint));

create policy "inv_embeddings_insert" on public.inv_embeddings
    for insert with check (tenant_id = (current_setting('app.tenant_id', true)::bigint));

create policy "inv_embeddings_update" on public.inv_embeddings
    for update using (tenant_id = (current_setting('app.tenant_id', true)::bigint));

create policy "inv_embeddings_delete" on public.inv_embeddings
    for delete using (tenant_id = (current_setting('app.tenant_id', true)::bigint));

-- 5. FUNCION RPC: busqueda semantica
create or replace function public.rpc_buscar_inventario(
    p_tenant_id bigint,
    p_embedding vector(1536),
    p_limit int default 10
)
returns table(
    categoria text,
    producto_id bigint,
    score float,
    texto_busqueda text
)
language plpgsql stable security definer
set search_path = ''
as $$
begin
    return query
    select
        e.categoria,
        e.producto_id,
        (1 - (e.embedding <=> p_embedding))::float as score,
        e.texto_busqueda
    from public.inv_embeddings e
    where e.tenant_id = p_tenant_id
      and e.embedding is not null
    order by e.embedding <=> p_embedding
    limit p_limit;
end;
$$;

-- 5. FUNCION RPC: listar IDs para reindexacion
create or replace function public.rpc_inv_ids_para_embedding(
    p_tenant_id bigint,
    p_categoria text
)
returns table(
    producto_id bigint
)
language plpgsql stable security definer
set search_path = ''
as $$
begin
    case p_categoria
        when 'accesorios' then
            return query select a.id::bigint from public.inv_accesorios a
                where a.tenant_id = p_tenant_id and a.deleted_at is null;
        when 'baterias' then
            return query select b.id::bigint from public.inv_baterias b
                where b.tenant_id = p_tenant_id and b.deleted_at is null;
        when 'pantallas' then
            return query select p.id::bigint from public.inv_pantallas p
                where p.tenant_id = p_tenant_id and p.deleted_at is null;
        when 'servicios' then
            return query select s.id::bigint from public.inv_servicios_generales s
                where s.tenant_id = p_tenant_id and s.deleted_at is null;
        else
            return;
    end case;
end;
$$;

commit;
