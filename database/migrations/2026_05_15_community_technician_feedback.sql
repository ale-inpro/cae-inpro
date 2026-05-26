-- Valoración comunidad ↔ técnico (persiste aunque la asignación pase a unassigned)

CREATE TABLE IF NOT EXISTS community_technician_feedback (
    community_id INTEGER NOT NULL REFERENCES communities(id) ON DELETE CASCADE,
    technician_id INTEGER NOT NULL REFERENCES technicians(id) ON DELETE CASCADE,
    sentiment VARCHAR(20) NOT NULL DEFAULT 'neutral'
        CHECK (sentiment IN ('preferred', 'neutral', 'not_preferred')),
    reason_category VARCHAR(40) NULL,
    comment VARCHAR(280) NULL,
    rated_by_user_id INTEGER NOT NULL REFERENCES users(id),
    rated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (community_id, technician_id)
);

CREATE INDEX IF NOT EXISTS idx_ct_feedback_comm ON community_technician_feedback(community_id);
CREATE INDEX IF NOT EXISTS idx_ct_feedback_tech ON community_technician_feedback(technician_id);

COMMENT ON TABLE community_technician_feedback IS 'Preferencia de la comunidad sobre un técnico (información para admin al asignar)';