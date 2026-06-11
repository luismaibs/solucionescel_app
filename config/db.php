<?php
include_once __DIR__ . '/env_loader.php';

// ═══════════════════════════════════════════════════════
//  SUPABASE CLIENT (singleton global)
// ═══════════════════════════════════════════════════════
if (!class_exists('SupabaseClient', false)) {
    require_once dirname(__DIR__) . '/src/Shared/SupabaseClient.php';
}

if (!function_exists('getSupabase')) {
    function getSupabase(): SupabaseClient
    {
        global $supabase;
        if (!isset($supabase)) {
            $supabase = new SupabaseClient();
        }
        return $supabase;
    }
}

// Instancia global (mismo objeto que getSupabase())
if (!isset($supabase)) {
    $supabase = getSupabase();
}

// Variables para JS — expuestas via window.REALTIME_CONFIG
// NUNCA exportar tokens JWT al HTML. El cliente JS los obtiene via
// session storage de supabase-js (persistSession: true + autoRefreshToken: true).
require_once __DIR__ . '/auth_jwt.php';
$supabase_anon_key_for_js = getenv('SUPABASE_ANON_KEY') ?: '';
$supabase_url_for_js = rtrim((string) getenv('SUPABASE_URL'), '/');
$tenant_id_for_js = (int) (getCurrentTenantId() ?? getenv('TENANT_ID_DEFAULT') ?: 1);
