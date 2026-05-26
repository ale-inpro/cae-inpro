-- Técnicos: columnas nuevas + migración de datos existentes
-- Tabla actual: first_name, last_name, dni_nie, birth_date, email, phone, professions, address, city, province, postal_code, is_active, ...
-- Todos los registros actuales pasan a entity_type = 'individual'.
-- NO elimina columnas antiguas (eso va en 2026_05_22_technicians_identity_contract.sql).

-- ── 1. Nuevas columnas ───────────────────────────────────────────────────────
ALTER TABLE technicians
    ADD COLUMN IF NOT EXISTS entity_type VARCHAR(20),
    ADD COLUMN IF NOT EXISTS tax_id VARCHAR(20),
    ADD COLUMN IF NOT EXISTS display_name VARCHAR(255);

-- ── 2. Backfill ─────────────────────────────────────────────────────────────

-- Todos los técnicos ya cargados = persona física / autónomo
UPDATE technicians
SET entity_type = 'individual';

-- Nombre completo desde first_name + last_name
UPDATE technicians
SET display_name = NULLIF(
    TRIM(
        CONCAT_WS(
            ' ',
            NULLIF(TRIM(first_name), ''),
            NULLIF(TRIM(last_name), '')
        )
    ),
    ''
);

UPDATE technicians
SET display_name = 'Técnico sin nombre'
WHERE display_name IS NULL OR TRIM(display_name) = '';

-- Identificador fiscal desde dni_nie (mayúsculas, sin espacios ni guiones)
UPDATE technicians
SET tax_id = UPPER(
    REGEXP_REPLACE(TRIM(COALESCE(dni_nie, '')), '[^A-Z0-9]', '', 'g')
)
WHERE dni_nie IS NOT NULL
  AND TRIM(dni_nie) <> '';

-- Comprobar que no quede ninguno sin tax_id antes de NOT NULL:
-- SELECT id, first_name, last_name, dni_nie, display_name, tax_id FROM technicians
-- WHERE tax_id IS NULL OR TRIM(tax_id) = '';

-- Comprobar duplicados (con tus 9 filas no debería haber; ejecutar igual):
-- SELECT tax_id, COUNT(*) AS cnt, array_agg(id ORDER BY id) AS ids
-- FROM technicians
-- GROUP BY tax_id
-- HAVING COUNT(*) > 1;

-- ── 3. Restricciones e índices ───────────────────────────────────────────────
ALTER TABLE technicians
    ALTER COLUMN entity_type SET DEFAULT 'individual';

ALTER TABLE technicians
    ALTER COLUMN entity_type SET NOT NULL,
    ALTER COLUMN tax_id SET NOT NULL,
    ALTER COLUMN display_name SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'technicians_entity_type_check'
    ) THEN
        ALTER TABLE technicians
            ADD CONSTRAINT technicians_entity_type_check
            CHECK (entity_type IN ('individual', 'company'));
    END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS idx_technicians_tax_id
    ON technicians (tax_id);

CREATE INDEX IF NOT EXISTS idx_technicians_entity_type
    ON technicians (entity_type);

CREATE INDEX IF NOT EXISTS idx_technicians_display_name
    ON technicians (display_name);

COMMENT ON COLUMN technicians.entity_type IS 'individual = persona física/autónomo; company = persona jurídica';
COMMENT ON COLUMN technicians.tax_id IS 'Identificador fiscal único: NIF/DNI/NIE o CIF (normalizado)';
COMMENT ON COLUMN technicians.display_name IS 'Nombre completo (individual) o razón social (company)';