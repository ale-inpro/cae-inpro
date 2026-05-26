-- Eliminar columnas legacy en technicians (ejecutar DESPUÉS del cambio de código)

ALTER TABLE technicians
    DROP COLUMN IF EXISTS birth_date;

ALTER TABLE technicians DROP CONSTRAINT IF EXISTS technicians_dni_nie_key;
DROP INDEX IF EXISTS idx_technicians_dni_nie;

ALTER TABLE technicians
    DROP COLUMN IF EXISTS first_name,
    DROP COLUMN IF EXISTS last_name,
    DROP COLUMN IF EXISTS dni_nie;