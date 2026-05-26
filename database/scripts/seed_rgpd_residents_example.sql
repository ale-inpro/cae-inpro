-- Vecinos de ejemplo (ajusta community_id al id real de tu comunidad)
INSERT INTO community_residents (
    community_id, nombre, apellidos, full_name, email, telefono, dni,
    unit_label, propiedades,
    enviar_email, enviar_whatsapp, enviar_postal, direccion_postal,
    es_representante, is_owner, is_president, is_active
) VALUES
(4, 'María', 'García López', 'María García López', 'maria.garcia@example.com', '+34600111222', '12345678A',
 '1º A', '{"vivienda":"1º A","coeficiente":0.08}'::jsonb,
 TRUE, TRUE, FALSE, NULL, FALSE, TRUE, TRUE, TRUE),
(4, 'Juan', 'Pérez Ruiz', 'Juan Pérez Ruiz', 'juan.perez@example.com', '+34600222333', '23456789B',
 '2º B', '{"vivienda":"2º B","coeficiente":0.06}'::jsonb,
 TRUE, FALSE, TRUE, 'C/ Mayor 15, 2º B, 28001 Madrid', FALSE, TRUE, FALSE, TRUE),
(4, 'Ana', 'Martínez Soto', 'Ana Martínez Soto', 'ana.martinez@example.com', '+34600333444', '34567890C',
 '3º C', '{"vivienda":"3º C","trastero":"T-3"}'::jsonb,
 FALSE, FALSE, TRUE, 'Av. de la Constitución 8, 3º C, 28012 Madrid', TRUE, TRUE, FALSE, TRUE);
