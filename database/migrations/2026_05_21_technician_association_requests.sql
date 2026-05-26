-- Solicitudes del gestor para asociar un técnico ya existente a su empresa gestora

CREATE TABLE IF NOT EXISTS technician_association_requests (
    id BIGSERIAL PRIMARY KEY,
    technician_id BIGINT NOT NULL REFERENCES technicians(id) ON DELETE CASCADE,
    manager_company_id INTEGER NOT NULL,
    requested_by_user_id INTEGER NOT NULL REFERENCES users(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'approved', 'rejected')),
    gestor_notes TEXT NULL,
    admin_notes TEXT NULL,
    reviewed_by_user_id INTEGER NULL REFERENCES users(id),
    reviewed_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_tech_assoc_req_one_pending
    ON technician_association_requests (technician_id, manager_company_id)
    WHERE status = 'pending';

CREATE INDEX IF NOT EXISTS idx_tech_assoc_req_mc_status
    ON technician_association_requests (manager_company_id, status);

CREATE INDEX IF NOT EXISTS idx_tech_assoc_req_status_created
    ON technician_association_requests (status, created_at DESC);

COMMENT ON TABLE technician_association_requests IS
    'Gestor solicita vincular técnico global existente; admin aprueba o rechaza';