-- Columnas para CSV extraído y resultado del cotejo AEAT (CotejoInternetV1)
ALTER TABLE cae_documents
    ADD COLUMN IF NOT EXISTS extracted_aeat_csv VARCHAR(16) NULL,
    ADD COLUMN IF NOT EXISTS aeat_cotejo_codigo VARCHAR(10) NULL,
    ADD COLUMN IF NOT EXISTS aeat_cotejo_descripcion TEXT NULL,
    ADD COLUMN IF NOT EXISTS aeat_cotejo_checked_at TIMESTAMPTZ NULL,
    ADD COLUMN IF NOT EXISTS aeat_cotejo_huella_ok BOOLEAN NULL,
    ADD COLUMN IF NOT EXISTS aeat_cotejo_http_code INTEGER NULL,
    ADD COLUMN IF NOT EXISTS aeat_cotejo_used_mock BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS aeat_cotejo_csv_sustituto VARCHAR(16) NULL,
    ADD COLUMN IF NOT EXISTS aeat_cotejo_curl_error TEXT NULL;