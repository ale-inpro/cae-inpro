-- =============================================================================
-- Migración: community_residents ≈ propietarios (otro proyecto)
-- Ejecutar DESPUÉS de 2026_05_23_rgpd_module.sql
-- =============================================================================

BEGIN;

-- 1) Nuevos campos (equivalentes a propietarios)
ALTER TABLE community_residents
    ADD COLUMN IF NOT EXISTS nombre VARCHAR(120),
    ADD COLUMN IF NOT EXISTS apellidos VARCHAR(120),
    ADD COLUMN IF NOT EXISTS telefono VARCHAR(40),
    ADD COLUMN IF NOT EXISTS propiedades JSONB,
    ADD COLUMN IF NOT EXISTS enviar_email BOOLEAN NOT NULL DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS enviar_whatsapp BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS direccion_postal TEXT,
    ADD COLUMN IF NOT EXISTS enviar_postal BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS dni TEXT,
    ADD COLUMN IF NOT EXISTS es_representante BOOLEAN NOT NULL DEFAULT FALSE;

-- 2) Renombrar phone → telefono (si aún existe phone)
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'community_residents'
          AND column_name = 'phone'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'community_residents'
          AND column_name = 'telefono'
    ) THEN
        ALTER TABLE community_residents RENAME COLUMN phone TO telefono;
    END IF;
END $$;

-- 3) Rellenar telefono desde phone si quedaron ambas por un reinicio a medias
UPDATE community_residents
SET telefono = COALESCE(telefono, phone)
WHERE telefono IS NULL AND phone IS NOT NULL;

-- 4) Partir full_name → nombre + apellidos (primera palabra / resto)
UPDATE community_residents
SET
    nombre = COALESCE(NULLIF(TRIM(nombre), ''), split_part(TRIM(full_name), ' ', 1)),
    apellidos = COALESCE(
        NULLIF(TRIM(apellidos), ''),
        NULLIF(TRIM(SUBSTRING(TRIM(full_name) FROM POSITION(' ' IN TRIM(full_name)) + 1)), ''),
        ''
    )
WHERE full_name IS NOT NULL AND TRIM(full_name) <> '';

-- Si solo hay un nombre sin apellido, apellidos queda ''
UPDATE community_residents
SET apellidos = ''
WHERE apellidos IS NULL;

-- 5) unit_label → propiedades.vivienda (como en el otro proyecto, datos flexibles)
UPDATE community_residents
SET propiedades = COALESCE(propiedades, '{}'::jsonb)
    || jsonb_build_object('vivienda', unit_label)
WHERE unit_label IS NOT NULL
  AND TRIM(unit_label) <> ''
  AND (propiedades IS NULL OR NOT (propiedades ? 'vivienda'));

-- 6) Email nullable (como propietarios)
ALTER TABLE community_residents
    ALTER COLUMN email DROP NOT NULL;

-- 7) NOT NULL en nombre/apellidos cuando ya hay datos
UPDATE community_residents
SET nombre = COALESCE(NULLIF(TRIM(nombre), ''), 'Sin nombre')
WHERE nombre IS NULL OR TRIM(nombre) = '';

UPDATE community_residents
SET apellidos = COALESCE(apellidos, '')
WHERE apellidos IS NULL;

ALTER TABLE community_residents
    ALTER COLUMN nombre SET NOT NULL,
    ALTER COLUMN apellidos SET NOT NULL;

-- 8) Mantener full_name sincronizado (compatibilidad con código PHP actual)
UPDATE community_residents
SET full_name = TRIM(nombre || ' ' || NULLIF(TRIM(apellidos), ''))
WHERE nombre IS NOT NULL;

-- 9) Índices útiles
CREATE INDEX IF NOT EXISTS idx_community_residents_dni
    ON community_residents(community_id, dni)
    WHERE dni IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_community_residents_nombre
    ON community_residents(community_id, nombre, apellidos);

COMMENT ON COLUMN community_residents.nombre IS 'Equivalente propietarios.nombre';
COMMENT ON COLUMN community_residents.apellidos IS 'Equivalente propietarios.apellidos';
COMMENT ON COLUMN community_residents.telefono IS 'Equivalente propietarios.telefono';
COMMENT ON COLUMN community_residents.propiedades IS 'Equivalente propietarios.propiedades (jsonb)';
COMMENT ON COLUMN community_residents.enviar_email IS 'Equivalente propietarios.enviar_email';
COMMENT ON COLUMN community_residents.enviar_whatsapp IS 'Equivalente propietarios.enviar_whatsapp';
COMMENT ON COLUMN community_residents.direccion_postal IS 'Equivalente propietarios.direccion_postal';
COMMENT ON COLUMN community_residents.enviar_postal IS 'Equivalente propietarios.enviar_postal';
COMMENT ON COLUMN community_residents.dni IS 'Equivalente propietarios.dni';
COMMENT ON COLUMN community_residents.es_representante IS 'Equivalente propietarios.es_representante (representante legal)';
COMMENT ON COLUMN community_residents.is_president IS 'Presidente de la comunidad (RGPD/cae-inpro)';
COMMENT ON COLUMN community_residents.community_id IS 'Equivalente propietarios.comunidad_id (INTEGER aquí)';

COMMIT;

-- Comprobación
SELECT column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_schema = 'public' AND table_name = 'community_residents'
ORDER BY ordinal_position;