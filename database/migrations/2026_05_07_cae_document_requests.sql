CREATE TABLE IF NOT EXISTS cae_document_requests (
    id BIGSERIAL PRIMARY KEY,
    technician_id BIGINT NOT NULL REFERENCES technicians(id),
    cae_record_id BIGINT NULL REFERENCES cae_records(id),
    requested_by_user_id BIGINT NOT NULL REFERENCES users(id),
    documents_requested_json JSONB NOT NULL,
    custom_message TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'sent', -- sent | failed | completed | cancelled
    email_error TEXT NULL,
    sent_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_cae_doc_req_tech_created
    ON cae_document_requests (technician_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_cae_doc_req_status
    ON cae_document_requests (status);

CREATE INDEX IF NOT EXISTS idx_cae_doc_req_cae_record
    ON cae_document_requests (cae_record_id);