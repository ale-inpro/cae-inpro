CREATE TABLE IF NOT EXISTS cae_ai_generations (
    id BIGSERIAL PRIMARY KEY,
    technician_id BIGINT NOT NULL REFERENCES technicians(id),
    cae_record_id BIGINT NULL REFERENCES cae_records(id),
    requested_by_user_id BIGINT NOT NULL REFERENCES users(id),
    model_name VARCHAR(120) NOT NULL DEFAULT 'gpt-4o-mini',
    status VARCHAR(20) NOT NULL DEFAULT 'generated', -- generated|failed
    input_json JSONB NOT NULL,
    output_json JSONB NULL,
    pdf_storage_path TEXT NULL,
    error_message TEXT NULL,
    generated_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_cae_ai_generations_tech
ON cae_ai_generations (technician_id, created_at DESC);

CREATE TABLE IF NOT EXISTS cae_ai_generation_sources (
    id BIGSERIAL PRIMARY KEY,
    generation_id BIGINT NOT NULL REFERENCES cae_ai_generations(id) ON DELETE CASCADE,
    source_type VARCHAR(20) NOT NULL, -- existing|upload
    cae_document_id BIGINT NULL REFERENCES cae_documents(id),
    original_filename TEXT NOT NULL,
    storage_path TEXT NULL,
    mime_type VARCHAR(120) NULL,
    extracted_text TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_cae_ai_sources_generation
ON cae_ai_generation_sources (generation_id);