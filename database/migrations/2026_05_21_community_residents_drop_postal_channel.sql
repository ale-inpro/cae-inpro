-- El envío RGPD es solo por email; se elimina el flag de canal postal.
BEGIN;

ALTER TABLE community_residents
    DROP COLUMN IF EXISTS enviar_postal;

COMMIT;
