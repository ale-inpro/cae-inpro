-- El canal de envío RGPD es únicamente email; se eliminan flags de canal obsoletos.
BEGIN;

ALTER TABLE community_residents
    DROP COLUMN IF EXISTS enviar_email,
    DROP COLUMN IF EXISTS enviar_whatsapp;

COMMIT;
