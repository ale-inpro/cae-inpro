-- Módulo RGPD (comunidades, vecinos, plantillas, firmas, contratos)

-- 1) Vecinos por comunidad
CREATE TABLE IF NOT EXISTS community_residents (
    id SERIAL PRIMARY KEY,
    community_id INTEGER NOT NULL REFERENCES communities(id) ON DELETE CASCADE,
    full_name VARCHAR(200) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(40) NULL,
    unit_label VARCHAR(80) NULL,
    is_owner BOOLEAN NOT NULL DEFAULT TRUE,
    is_president BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_community_residents_comm ON community_residents(community_id);
CREATE INDEX IF NOT EXISTS idx_community_residents_email ON community_residents(community_id, email);

-- Solo un presidente activo por comunidad
CREATE UNIQUE INDEX IF NOT EXISTS uq_community_one_president
    ON community_residents(community_id)
    WHERE is_president = TRUE AND is_active = TRUE;

-- 2) Plantillas (sistema = solo lectura en app; user = CRUD admin)
CREATE TABLE IF NOT EXISTS rgpd_templates (
    id SERIAL PRIMARY KEY,
    kind VARCHAR(20) NOT NULL DEFAULT 'user'
        CHECK (kind IN ('system', 'user')),
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(120) NULL,
    category VARCHAR(80) NOT NULL DEFAULT 'consentimiento',
    description TEXT NULL,
    body_html TEXT NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by_user_id INTEGER NULL REFERENCES users(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_rgpd_templates_slug ON rgpd_templates(slug) WHERE slug IS NOT NULL;

-- 3) Contrato RGPD por comunidad (1 activo; renovable)
CREATE TABLE IF NOT EXISTS community_rgpd_contracts (
    id SERIAL PRIMARY KEY,
    community_id INTEGER NOT NULL REFERENCES communities(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'active', 'expired')),
    signed_at DATE NULL,
    expires_at DATE NULL,
    storage_path VARCHAR(500) NULL,
    original_filename VARCHAR(255) NULL,
    signed_on_paper BOOLEAN NOT NULL DEFAULT FALSE,
    paper_notes TEXT NULL,
    uploaded_by_user_id INTEGER NULL REFERENCES users(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_rgpd_contracts_comm ON community_rgpd_contracts(community_id);

-- 4) Campaña de envío masivo
CREATE TABLE IF NOT EXISTS rgpd_campaigns (
    id SERIAL PRIMARY KEY,
    community_id INTEGER NOT NULL REFERENCES communities(id) ON DELETE CASCADE,
    created_by_user_id INTEGER NOT NULL REFERENCES users(id),
    audience VARCHAR(20) NOT NULL DEFAULT 'both'
        CHECK (audience IN ('owners', 'presidents', 'both')),
    status VARCHAR(20) NOT NULL DEFAULT 'draft'
        CHECK (status IN ('draft', 'sending', 'completed', 'cancelled')),
    notes TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMPTZ NULL
);

CREATE INDEX IF NOT EXISTS idx_rgpd_campaigns_comm ON rgpd_campaigns(community_id);

CREATE TABLE IF NOT EXISTS rgpd_campaign_templates (
    campaign_id INTEGER NOT NULL REFERENCES rgpd_campaigns(id) ON DELETE CASCADE,
    template_id INTEGER NOT NULL REFERENCES rgpd_templates(id) ON DELETE RESTRICT,
    PRIMARY KEY (campaign_id, template_id)
);

-- 5) Solicitud de firma (1 fila = 1 vecino + 1 plantilla)
CREATE TABLE IF NOT EXISTS rgpd_signature_requests (
    id SERIAL PRIMARY KEY,
    campaign_id INTEGER NULL REFERENCES rgpd_campaigns(id) ON DELETE SET NULL,
    community_id INTEGER NOT NULL REFERENCES communities(id) ON DELETE CASCADE,
    resident_id INTEGER NOT NULL REFERENCES community_residents(id) ON DELETE CASCADE,
    template_id INTEGER NOT NULL REFERENCES rgpd_templates(id) ON DELETE RESTRICT,
    token VARCHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'signed', 'paper', 'cancelled')),
    rendered_html TEXT NOT NULL,
    signature_image_path VARCHAR(500) NULL,
    signer_ip VARCHAR(45) NULL,
    signer_user_agent VARCHAR(500) NULL,
    signed_at TIMESTAMPTZ NULL,
    signed_on_paper BOOLEAN NOT NULL DEFAULT FALSE,
    paper_notes TEXT NULL,
    paper_recorded_by_user_id INTEGER NULL REFERENCES users(id),
    email_sent_at TIMESTAMPTZ NULL,
    resent_count INTEGER NOT NULL DEFAULT 0,
    token_expires_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (token)
);

CREATE INDEX IF NOT EXISTS idx_rgpd_sig_req_comm ON rgpd_signature_requests(community_id);
CREATE INDEX IF NOT EXISTS idx_rgpd_sig_req_resident ON rgpd_signature_requests(resident_id);
CREATE INDEX IF NOT EXISTS idx_rgpd_sig_req_status ON rgpd_signature_requests(status);

-- Evitar duplicar pendiente misma plantilla+vecino (opcional pero útil)
CREATE UNIQUE INDEX IF NOT EXISTS uq_rgpd_sig_pending_pair
    ON rgpd_signature_requests(resident_id, template_id)
    WHERE status = 'pending';

COMMENT ON TABLE community_residents IS 'Propietarios/vecinos de una comunidad (alta manual/script)';
COMMENT ON TABLE rgpd_templates IS 'Plantillas de consentimiento RGPD (system|user)';
COMMENT ON TABLE community_rgpd_contracts IS 'Contrato marco RGPD/encargo por comunidad';
COMMENT ON TABLE rgpd_signature_requests IS 'Firma casera: enlace, canvas, IP, o registro en papel';