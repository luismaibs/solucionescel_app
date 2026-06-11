-- ============================================================================
-- Funcion: tiene_permiso(p_rol, p_slug)
-- Reemplaza 3 queries separadas en PHP por una sola llamada RPC.
-- Retorna TRUE si el rol tiene el permiso dado, FALSE en caso contrario.
-- ============================================================================

CREATE OR REPLACE FUNCTION tiene_permiso(p_rol text, p_slug text)
RETURNS boolean
LANGUAGE sql STABLE SECURITY DEFINER
AS $$
  SELECT EXISTS (
    SELECT 1
    FROM   role_permiso rp
    JOIN   roles        r ON r.id = rp.role_id
    JOIN   permisos     p ON p.id = rp.permiso_id
    WHERE  r.slug = p_rol
    AND    p.slug = p_slug
  );
$$;
