-- Migration 0018: agrega columna `seleccionable` a estados_config
-- Un estado con seleccionable=true puede elegirse directamente (notifica a n8n inmediatamente).
-- Un estado con seleccionable=false requiere que el usuario elija un sub-estado obligatoriamente.
--
-- No es necesario recrear rpc_estados_tree porque ya usa row_to_json(e) que devuelve
-- todas las columnas de la tabla automáticamente (incluyendo la nueva).

ALTER TABLE estados_config
ADD COLUMN IF NOT EXISTS seleccionable BOOLEAN NOT NULL DEFAULT TRUE;
