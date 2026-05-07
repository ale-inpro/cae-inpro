-- Migración: añade columna read_at a notifications si no existe
-- Ejecutar una sola vez en la BD cae_inpro

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS read_at TIMESTAMPTZ NULL;

-- También asegurarse de que la columna message exista
-- (se añadió en la migración anterior renombrando body → message)
-- Esta línea es segura si ya existe gracias a IF NOT EXISTS:
-- (PostgreSQL no tiene ADD COLUMN IF NOT EXISTS para renombrar, ya se hizo antes)
