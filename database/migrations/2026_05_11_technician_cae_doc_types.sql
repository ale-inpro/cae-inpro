BEGIN;

-- 1) Asegurar que existen los 4 tipos oficiales (documentos complementarios técnico CAE)
INSERT INTO document_types (name, scope, is_cae_file_type, is_active)
SELECT 'Certificado de estar al corriente con Hacienda', 'technician_cae', FALSE, TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM document_types
    WHERE scope = 'technician_cae'
      AND is_cae_file_type = FALSE
      AND name = 'Certificado de estar al corriente con Hacienda'
);

INSERT INTO document_types (name, scope, is_cae_file_type, is_active)
SELECT 'Certificado de estar al corriente con Seguridad Social', 'technician_cae', FALSE, TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM document_types
    WHERE scope = 'technician_cae'
      AND is_cae_file_type = FALSE
      AND name = 'Certificado de estar al corriente con Seguridad Social'
);

INSERT INTO document_types (name, scope, is_cae_file_type, is_active)
SELECT 'Póliza RC', 'technician_cae', FALSE, TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM document_types
    WHERE scope = 'technician_cae'
      AND is_cae_file_type = FALSE
      AND name = 'Póliza RC'
);

INSERT INTO document_types (name, scope, is_cae_file_type, is_active)
SELECT 'Recibo RC', 'technician_cae', FALSE, TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM document_types
    WHERE scope = 'technician_cae'
      AND is_cae_file_type = FALSE
      AND name = 'Recibo RC'
);

-- 2) Activar explícitamente los 4 oficiales
UPDATE document_types
SET is_active = TRUE
WHERE scope = 'technician_cae'
  AND is_cae_file_type = FALSE
  AND name IN (
    'Certificado de estar al corriente con Hacienda',
    'Certificado de estar al corriente con Seguridad Social',
    'Póliza RC',
    'Recibo RC'
  );

-- 3) Desactivar cualquier otro tipo complementario de technician_cae
UPDATE document_types
SET is_active = FALSE
WHERE scope = 'technician_cae'
  AND is_cae_file_type = FALSE
  AND name NOT IN (
    'Certificado de estar al corriente con Hacienda',
    'Certificado de estar al corriente con Seguridad Social',
    'Póliza RC',
    'Recibo RC'
  );

COMMIT;