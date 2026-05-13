BEGIN;

CREATE TABLE IF NOT EXISTS cae_document_intake (
    id BIGSERIAL PRIMARY KEY,
    technician_id BIGINT NOT NULL REFERENCES technicians(id),
    cae_record_id BIGINT NOT NULL REFERENCES cae_records(id),
    document_type_id BIGINT NOT NULL REFERENCES document_types(id),

    original_filename VARCHAR(255) NOT NULL,
    storage_path TEXT NOT NULL,
    mime_type VARCHAR(120),
    file_size BIGINT NOT NULL DEFAULT 0,

    source_channel VARCHAR(20) NOT NULL CHECK (source_channel IN ('admin_upload','portal_upload')),
    uploaded_by_user_id BIGINT NULL REFERENCES users(id),

    extracted_text TEXT NULL,
    ai_status VARCHAR(20) NOT NULL DEFAULT 'manual_review' CHECK (ai_status IN ('approved','in_review','rejected','manual_review')),
    ai_confidence NUMERIC(5,4) NULL,
    ai_issue_date DATE NULL,
    ai_expires_at DATE NULL,
    ai_notes TEXT NULL,

    status VARCHAR(30) NOT NULL DEFAULT 'pending_manual' CHECK (status IN ('pending_manual','approved_auto','approved_manual','rejected')),
    requires_manual_review BOOLEAN NOT NULL DEFAULT TRUE,

    reviewed_by_user_id BIGINT NULL REFERENCES users(id),
    reviewed_at TIMESTAMPTZ NULL,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_cae_doc_intake_tech_status
    ON cae_document_intake (technician_id, status, created_at DESC);

COMMIT;