-- =============================================================================
-- Migracion 0023: Politicas RLS para inv_pantallas
-- RLS fue habilitado en esta tabla (via Dashboard) pero sin politicas,
-- bloqueando INSERTs con JWT de usuario. Agrega politicas consistentes
-- con el patron de otras tablas (usuarios, ai_analisis_historial).
-- =============================================================================
begin;

alter table public.inv_pantallas enable row level security;

-- Usuarios autenticados solo acceden a su propio tenant
drop policy if exists inv_pantallas_tenant on public.inv_pantallas;
create policy inv_pantallas_tenant on public.inv_pantallas
for all
to authenticated
using  (tenant_id::text = coalesce(auth.jwt() ->> 'tenant_id', ''))
with check (tenant_id::text = coalesce(auth.jwt() ->> 'tenant_id', ''));

-- Service role bypasa RLS (usado por cron/webhooks/PHP sin JWT)
drop policy if exists inv_pantallas_service on public.inv_pantallas;
create policy inv_pantallas_service on public.inv_pantallas
for all to service_role using (true) with check (true);

commit;
