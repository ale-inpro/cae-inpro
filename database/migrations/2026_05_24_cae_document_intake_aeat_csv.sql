-- CSV detectado o indicado manualmente en revisión de intake (solo Hacienda)
ALTER TABLE cae_document_intake
    ADD COLUMN IF NOT EXISTS extracted_aeat_csv VARCHAR(16) NULL,
    ADD COLUMN IF NOT EXISTS manual_aeat_csv VARCHAR(16) NULL;

COMMENT ON COLUMN cae_document_intake.extracted_aeat_csv IS 'CSV AEAT auto-detectado al subir (Hacienda)';
COMMENT ON COLUMN cae_document_intake.manual_aeat_csv IS 'CSV AEAT indicado por admin al aprobar intake';