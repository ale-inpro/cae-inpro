-- Paso 3: como mucho un documento complementario activo por (cae_record_id, document_type_id).
BEGIN;

WITH keepers AS (
    SELECT DISTINCT ON (cae_record_id, document_type_id) id
    FROM cae_documents
    WHERE is_cae_file = FALSE
      AND is_active = TRUE
    ORDER BY cae_record_id, document_type_id, uploaded_at DESC NULLS LAST, id DESC
)
UPDATE cae_documents cd
SET is_active = FALSE,
    updated_at = NOW()
WHERE cd.is_cae_file = FALSE
  AND cd.is_active = TRUE
  AND cd.id NOT IN (SELECT id FROM keepers);

CREATE UNIQUE INDEX IF NOT EXISTS uq_cae_documents_one_active_supporting_slot
    ON cae_documents (cae_record_id, document_type_id)
    WHERE is_active = TRUE AND is_cae_file = FALSE;

COMMIT;