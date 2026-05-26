-- Actualiza vecinos insertados antes de vecinos_actalia.sql con datos de prueba completos.
-- Ajusta community_id (4) si tu comunidad tiene otro id.

BEGIN;

UPDATE community_residents SET
    nombre = 'María',
    apellidos = 'García López',
    full_name = 'María García López',
    email = 'maria.garcia@example.com',
    telefono = '+34600111222',
    dni = '12345678A',
    unit_label = '1º A',
    propiedades = '{"vivienda":"1º A","coeficiente":0.08,"garaje":"G-12"}'::jsonb,
    enviar_email = TRUE,
    enviar_whatsapp = TRUE,
    enviar_postal = FALSE,
    direccion_postal = NULL,
    es_representante = FALSE,
    is_owner = TRUE,
    is_president = TRUE,
    is_active = TRUE,
    updated_at = NOW()
WHERE community_id = 4
  AND (full_name ILIKE '%María%García%' OR email ILIKE '%maria.garcia%' OR nombre = 'María');

UPDATE community_residents SET
    nombre = 'Juan',
    apellidos = 'Pérez Ruiz',
    full_name = 'Juan Pérez Ruiz',
    email = 'juan.perez@example.com',
    telefono = '+34600222333',
    dni = '23456789B',
    unit_label = '2º B',
    propiedades = '{"vivienda":"2º B","coeficiente":0.06}'::jsonb,
    enviar_email = TRUE,
    enviar_whatsapp = FALSE,
    enviar_postal = TRUE,
    direccion_postal = 'C/ Mayor 15, 2º B, 28001 Madrid',
    es_representante = FALSE,
    is_owner = TRUE,
    is_president = FALSE,
    is_active = TRUE,
    updated_at = NOW()
WHERE community_id = 4
  AND (full_name ILIKE '%Juan%Pérez%' OR email ILIKE '%juan.perez%' OR nombre = 'Juan');

UPDATE community_residents SET
    nombre = 'Ana',
    apellidos = 'Martínez Soto',
    full_name = 'Ana Martínez Soto',
    email = 'ana.martinez@example.com',
    telefono = '+34600333444',
    dni = '34567890C',
    unit_label = '3º C',
    propiedades = '{"vivienda":"3º C","coeficiente":0.05,"trastero":"T-3"}'::jsonb,
    enviar_email = FALSE,
    enviar_whatsapp = FALSE,
    enviar_postal = TRUE,
    direccion_postal = 'Av. de la Constitución 8, 3º C, 28012 Madrid',
    es_representante = TRUE,
    is_owner = TRUE,
    is_president = FALSE,
    is_active = TRUE,
    updated_at = NOW()
WHERE community_id = 4
  AND (full_name ILIKE '%Ana%Martínez%' OR email ILIKE '%ana.martinez%' OR nombre = 'Ana');

COMMIT;

SELECT id, nombre, apellidos, email, telefono, enviar_email, enviar_postal, is_president, es_representante
FROM community_residents
WHERE community_id = 4
ORDER BY is_president DESC, nombre;
