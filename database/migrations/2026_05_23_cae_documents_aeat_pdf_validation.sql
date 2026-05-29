-- Validación del PDF oficial devuelto por AEAT (post-cotejo)
ALTER TABLE cae_documents
    ADD COLUMN IF NOT EXISTS aeat_pdf_validation_ok BOOLEAN NULL,
    ADD COLUMN IF NOT EXISTS aeat_pdf_validation_errors TEXT NULL,
    ADD COLUMN IF NOT EXISTS aeat_replaced_upload BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS aeat_upload_backup_path VARCHAR(512) NULL,
    ADD COLUMN IF NOT EXISTS aeat_official_sha1 VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS aeat_parsed_tax_id VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS aeat_parsed_expires_at DATE NULL;

COMMENT ON COLUMN cae_documents.aeat_pdf_validation_ok IS 'TRUE si el PDF oficial AEAT supera validación de contenido (NIF, al corriente, vigencia)';
COMMENT ON COLUMN cae_documents.aeat_pdf_validation_errors IS 'JSON array de strings con motivos de rechazo';
COMMENT ON COLUMN cae_documents.aeat_replaced_upload IS 'TRUE si storage_path apunta al PDF oficial AEAT (escaneo respaldado en aeat_upload_backup_path)';