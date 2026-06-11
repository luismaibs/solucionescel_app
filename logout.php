<?php
// Cierre de sesion — delegado a config/auth.php que maneja Supabase + sesion PHP
include 'config/auth.php';

// Si llegaron aqui sin ?logout, redirigir
if (!isset($_GET['logout'])) {
    header("Location: login");
    exit;
}
// auth.php ya maneja el logout completo (Supabase signOut + sesion + redirect)
