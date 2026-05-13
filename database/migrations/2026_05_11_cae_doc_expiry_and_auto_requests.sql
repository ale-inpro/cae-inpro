BEGIN;

-- A) Fecha de caducidad por documento CAE complementario
ALTER TABLE cae_documents
    ADD COLUMN IF NOT EXISTS expires_at DATE NULL;

CREATE INDEX IF NOT EXISTS idx_cae_documents_expires_at
    ON cae_documents (expires_at)
    WHERE is_active = TRUE
      AND is_cae_file = FALSE
      AND expires_at IS NOT NULL;

-- B) Marcar si una solicitud fue automática (para evitar duplicados)
ALTER TABLE cae_document_requests
    ADD COLUMN IF NOT EXISTS auto_generated BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS auto_reason TEXT NULL;

CREATE INDEX IF NOT EXISTS idx_cae_doc_requests_auto
    ON cae_document_requests (technician_id, auto_generated, created_at);

COMMIT;