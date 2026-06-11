-- =============================================================================
-- 0017: Corregir slugs de estados_config para que coincidan con los valores
--       legacy de reparaciones.estado y tipo_garantia.
--
-- El rpc_initialize_estados_default original usó slugs genéricos (status_*,
-- sub_listo_*) que no coinciden con los valores reales almacenados en la
-- columna reparaciones.estado ni en reparacion_garantias.tipo_garantia.
-- =============================================================================

DO $$
DECLARE
    v_tid      bigint;
    v_listo_id uuid;
BEGIN
    FOR v_tid IN
        SELECT DISTINCT tenant_id
        FROM public.estados_config
        WHERE slug IN (
            'status_laboratorio','status_listo','status_no_quedo','status_entregado',
            'sub_listo_garantia_prov_30','sub_listo_garantia_30',
            'sub_listo_garantia_60','sub_listo_garantia_90','sub_listo_sin_garantia',
            'status_garantia_exitosa','status_garantia_fallida','status_garantia_entregada'
        )
    LOOP
        -- Estados padre (Primer Ingreso)
        UPDATE public.estados_config
        SET slug = 'en_taller', nombre = 'En Taller'
        WHERE tenant_id = v_tid AND slug = 'status_laboratorio';

        UPDATE public.estados_config
        SET slug = 'listo', nombre = 'Listo'
        WHERE tenant_id = v_tid AND slug = 'status_listo';

        UPDATE public.estados_config
        SET slug = 'no_quedo', nombre = 'No Quedó'
        WHERE tenant_id = v_tid AND slug = 'status_no_quedo';

        UPDATE public.estados_config
        SET slug = 'entregado', nombre = 'Entregado'
        WHERE tenant_id = v_tid AND slug = 'status_entregado';

        -- Estados padre (Re Ingreso)
        UPDATE public.estados_config
        SET slug = 'garantia_finalizada', nombre = 'Garantía Exitosa'
        WHERE tenant_id = v_tid AND slug = 'status_garantia_exitosa';

        UPDATE public.estados_config
        SET slug = 'garantia_fallida', nombre = 'Garantía Fallida'
        WHERE tenant_id = v_tid AND slug = 'status_garantia_fallida';

        UPDATE public.estados_config
        SET slug = 'garantia_entregada', nombre = 'Garantía Entregada'
        WHERE tenant_id = v_tid AND slug = 'status_garantia_entregada';

        -- Subestados de Listo → slugs deben coincidir con tipo_garantia en reparacion_garantias
        SELECT id INTO v_listo_id
        FROM public.estados_config
        WHERE tenant_id = v_tid AND slug = 'listo';

        IF v_listo_id IS NOT NULL THEN
            UPDATE public.estados_config
            SET slug = 'garantia_tecnica_proveedor_30',
                nombre = 'Garantía técnica y proveedor 30 días',
                parent_id = v_listo_id
            WHERE tenant_id = v_tid AND slug = 'sub_listo_garantia_prov_30';

            UPDATE public.estados_config
            SET slug = 'garantia_30_dias',
                nombre = 'Garantía técnica 30 días',
                parent_id = v_listo_id
            WHERE tenant_id = v_tid AND slug = 'sub_listo_garantia_30';

            UPDATE public.estados_config
            SET slug = 'garantia_60_dias',
                nombre = 'Garantía técnica 60 días',
                parent_id = v_listo_id
            WHERE tenant_id = v_tid AND slug = 'sub_listo_garantia_60';

            UPDATE public.estados_config
            SET slug = 'garantia_90_dias',
                nombre = 'Garantía técnica 90 días',
                parent_id = v_listo_id
            WHERE tenant_id = v_tid AND slug = 'sub_listo_garantia_90';

            UPDATE public.estados_config
            SET slug = 'sin_garantia',
                nombre = 'Sin garantía',
                parent_id = v_listo_id
            WHERE tenant_id = v_tid AND slug = 'sub_listo_sin_garantia';
        END IF;

        -- Insertar estado garantia_activada si no existe (Re Ingreso)
        INSERT INTO public.estados_config
            (tenant_id, slug, nombre, descripcion, color, tipo, habilitar_reingreso, orden)
        SELECT
            v_tid,
            'garantia_activada',
            'Proceso de revisión técnica',
            'Garantía activada, dispositivo en revisión',
            '#0d9488',
            're_ingreso',
            false,
            -1
        WHERE NOT EXISTS (
            SELECT 1 FROM public.estados_config
            WHERE tenant_id = v_tid AND slug = 'garantia_activada'
        );

    END LOOP;
END $$;

-- Actualizar rpc_initialize_estados_default para usar slugs correctos en futuros tenants
CREATE OR REPLACE FUNCTION public.rpc_initialize_estados_default(p_tenant_id bigint)
RETURNS jsonb
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
    v_listo_id uuid;
    v_count    int;
BEGIN
    SELECT count(*) INTO v_count FROM public.estados_config WHERE tenant_id = p_tenant_id;
    IF v_count > 0 THEN
        RETURN jsonb_build_object('ok', true, 'message', 'Ya existen estados configurados', 'count', v_count);
    END IF;

    -- Primer Ingreso
    INSERT INTO estados_config (tenant_id, slug, nombre, descripcion, color, tipo, habilitar_reingreso, orden) VALUES
    (p_tenant_id, 'en_taller',  'En Taller',  'Equipo en proceso de reparación',              '#3b82f6', 'primer_ingreso', false, 0),
    (p_tenant_id, 'listo',      'Listo',       'Reparación exitosa, listo para entrega',        '#22c55e', 'primer_ingreso', true,  1),
    (p_tenant_id, 'no_quedo',   'No Quedó',    'No se pudo reparar',                            '#f43f5e', 'primer_ingreso', true,  2),
    (p_tenant_id, 'entregado',  'Entregado',   'Equipo entregado al cliente',                   '#64748b', 'primer_ingreso', false, 3);

    SELECT id INTO v_listo_id FROM estados_config WHERE tenant_id = p_tenant_id AND slug = 'listo';

    -- Subestados de Listo (coinciden con tipo_garantia en reparacion_garantias)
    INSERT INTO estados_config (tenant_id, parent_id, slug, nombre, color, tipo, orden) VALUES
    (p_tenant_id, v_listo_id, 'garantia_tecnica_proveedor_30', 'Garantía técnica y proveedor 30 días', '#22c55e', 'primer_ingreso', 0),
    (p_tenant_id, v_listo_id, 'garantia_30_dias',              'Garantía técnica 30 días',             '#22c55e', 'primer_ingreso', 1),
    (p_tenant_id, v_listo_id, 'garantia_60_dias',              'Garantía técnica 60 días',             '#22c55e', 'primer_ingreso', 2),
    (p_tenant_id, v_listo_id, 'garantia_90_dias',              'Garantía técnica 90 días',             '#22c55e', 'primer_ingreso', 3),
    (p_tenant_id, v_listo_id, 'sin_garantia',                  'Sin garantía',                         '#22c55e', 'primer_ingreso', 4);

    -- Re Ingreso
    INSERT INTO estados_config (tenant_id, slug, nombre, descripcion, color, tipo, orden) VALUES
    (p_tenant_id, 'garantia_activada',   'Proceso de revisión técnica', 'Garantía activada, en revisión',        '#0d9488', 're_ingreso', 0),
    (p_tenant_id, 'garantia_finalizada', 'Garantía Exitosa',            'Garantía resuelta satisfactoriamente',  '#a855f7', 're_ingreso', 1),
    (p_tenant_id, 'garantia_fallida',    'Garantía Fallida',            'Garantía no resuelta',                  '#dc2626', 're_ingreso', 2),
    (p_tenant_id, 'garantia_entregada',  'Garantía Entregada',          'Equipo en garantía entregado',          '#334155', 're_ingreso', 3);

    RETURN jsonb_build_object('ok', true, 'message', 'Estados inicializados correctamente', 'count', 13);
END;
$$;
