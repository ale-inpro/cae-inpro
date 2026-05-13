BEGIN;

-- Renombrar tipos existentes a los nombres reales
UPDATE document_types
SET name = 'Póliza de Responsabilidad Civil', updated_at = NOW()
WHERE scope = 'technician_cae'
  AND is_cae_file_type = FALSE
  AND name = 'Póliza RC';

UPDATE document_types
SET name = 'Certificado de Prevención de Riesgos Laborales', updated_at = NOW()
WHERE scope = 'technician_cae'
  AND is_cae_file_type = FALSE
  AND name = 'Recibo RC';

-- Asegurar que los 4 tipos oficiales están activos con los nombres correctos
UPDATE document_types
SET is_active = TRUE, updated_at = NOW()
WHERE scope = 'technician_cae'
  AND is_cae_file_type = FALSE
  AND name IN (
    'Certificado de estar al corriente con Hacienda',
    'Certificado de estar al corriente con Seguridad Social',
    'Póliza de Responsabilidad Civil',
    'Certificado de Prevención de Riesgos Laborales'
  );

-- Desactivar cualquier otro tipo complementario sobrante
UPDATE document_types
SET is_active = FALSE, updated_at = NOW()
WHERE scope = 'technician_cae'
  AND is_cae_file_type = FALSE
  AND name NOT IN (
    'Certificado de estar al corriente con Hacienda',
    'Certificado de estar al corriente con Seguridad Social',
    'Póliza de Responsabilidad Civil',
    'Certificado de Prevención de Riesgos Laborales'
  );

COMMIT;