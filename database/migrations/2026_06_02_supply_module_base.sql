-- Módulo Suministros (contratos por comunidad o vecino)

-- 1) Empresas de suministro (comercializadoras/distribuidoras)
CREATE TABLE IF NOT EXISTS supply_companies (
    id SERIAL PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    company_role VARCHAR(20) NOT NULL DEFAULT 'mixed'
        CHECK (company_role IN ('marketer', 'distributor', 'mixed')),
    phone VARCHAR(40) NULL,
    email VARCHAR(180) NULL,
    website VARCHAR(255) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_supply_companies_name ON supply_companies(name);
CREATE INDEX IF NOT EXISTS idx_supply_companies_role ON supply_companies(company_role);

-- 2) Contratos de suministro (scope: comunidad o vecino)
CREATE TABLE IF NOT EXISTS supply_contracts (
    id SERIAL PRIMARY KEY,

    scope VARCHAR(20) NOT NULL
        CHECK (scope IN ('community', 'resident')),

    community_id INTEGER NULL REFERENCES communities(id) ON DELETE CASCADE,
    resident_id INTEGER NULL REFERENCES community_residents(id) ON DELETE CASCADE,

    supply_type VARCHAR(30) NOT NULL
        CHECK (supply_type IN ('electricity', 'gas', 'water', 'telecom', 'other')),

    marketer_company_id INTEGER NULL REFERENCES supply_companies(id) ON DELETE SET NULL,
    distributor_company_id INTEGER NULL REFERENCES supply_companies(id) ON DELETE SET NULL,

    contract_number VARCHAR(120) NOT NULL,
    cups VARCHAR(32) NULL,

    start_date DATE NOT NULL,
    end_date DATE NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'active'
        CHECK (status IN ('active', 'pending_renewal', 'expired', 'cancelled', 'draft')),

    auto_renew BOOLEAN NOT NULL DEFAULT FALSE,
    admin_fee_eur NUMERIC(12,2) NOT NULL DEFAULT 0.00,

    supply_address TEXT NOT NULL,
    notes TEXT NULL,

    created_by_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    updated_by_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,

    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT ck_supply_scope_target CHECK (
        (scope = 'community' AND community_id IS NOT NULL AND resident_id IS NULL) OR
        (scope = 'resident'  AND resident_id IS NOT NULL AND community_id IS NULL)
    ),

    CONSTRAINT ck_supply_dates CHECK (
        end_date IS NULL OR end_date >= start_date
    )
);

CREATE INDEX IF NOT EXISTS idx_supply_contracts_scope ON supply_contracts(scope);
CREATE INDEX IF NOT EXISTS idx_supply_contracts_community ON supply_contracts(community_id);
CREATE INDEX IF NOT EXISTS idx_supply_contracts_resident ON supply_contracts(resident_id);
CREATE INDEX IF NOT EXISTS idx_supply_contracts_status ON supply_contracts(status);
CREATE INDEX IF NOT EXISTS idx_supply_contracts_end_date ON supply_contracts(end_date);
CREATE INDEX IF NOT EXISTS idx_supply_contracts_marketer ON supply_contracts(marketer_company_id);
CREATE INDEX IF NOT EXISTS idx_supply_contracts_distributor ON supply_contracts(distributor_company_id);

-- Evita duplicado de contrato activo por ámbito + tipo + CUPS normalizado
CREATE UNIQUE INDEX IF NOT EXISTS uq_supply_active_contract
    ON supply_contracts (
        scope,
        COALESCE(community_id, 0),
        COALESCE(resident_id, 0),
        supply_type,
        UPPER(COALESCE(cups, ''))
    )
    WHERE status IN ('active', 'pending_renewal');

-- 3) Documentos del contrato (PDF/anexos)
CREATE TABLE IF NOT EXISTS supply_contract_documents (
    id SERIAL PRIMARY KEY,
    contract_id INTEGER NOT NULL REFERENCES supply_contracts(id) ON DELETE CASCADE,
    storage_path VARCHAR(500) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NULL,
    file_size_bytes BIGINT NULL,
    uploaded_by_user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_supply_contract_docs_contract ON supply_contract_documents(contract_id);

COMMENT ON TABLE supply_companies IS 'Empresas comercializadoras/distribuidoras de suministros';
COMMENT ON TABLE supply_contracts IS 'Contratos de suministro para comunidades o vecinos';
COMMENT ON TABLE supply_contract_documents IS 'Documentos PDF/anexos asociados a contratos de suministro';