-- =============================================================================
-- Migracion 0006: Tablas auxiliares (plantillas WhatsApp, telemetria)
-- =============================================================================
begin;

-- Plantillas de WhatsApp (antes SQLite)
create table if not exists public.whatsapp_templates (
  id bigserial primary key,
  title text not null,
  content text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

-- Registro de llamadas a APIs externas (DeepSeek, n8n, etc.) para monitoreo
create table if not exists public.external_api_logs (
  id bigserial primary key,
  service text not null,
  endpoint text not null,
  method text not null,
  status_code int,
  ok boolean not null default false,
  response_time_ms int,
  tokens_used int,
  model text,
  error_message text,
  created_at timestamptz not null default now()
);

-- RLS: solo lectura para monitoreo (admin)
alter table public.whatsapp_templates enable row level security;
drop policy if exists p_whatsapp_templates_read on public.whatsapp_templates;
create policy p_whatsapp_templates_read on public.whatsapp_templates
  for select to authenticated using (true);
drop policy if exists p_whatsapp_templates_write on public.whatsapp_templates;
create policy p_whatsapp_templates_write on public.whatsapp_templates
  for all to authenticated
  using (coalesce(auth.jwt() ->> 'rol', '') = 'admin')
  with check (coalesce(auth.jwt() ->> 'rol', '') = 'admin');

alter table public.external_api_logs enable row level security;
drop policy if exists p_external_api_logs_read on public.external_api_logs;
create policy p_external_api_logs_read on public.external_api_logs
  for select to authenticated using (true);
drop policy if exists p_external_api_logs_write on public.external_api_logs;
create policy p_external_api_logs_write on public.external_api_logs
  for insert to authenticated
  with check (true);

commit;
