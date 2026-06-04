-- Firma en papel: PDF escaneado obligatorio
ALTER TABLE rgpd_signature_requests
    ADD COLUMN IF NOT EXISTS paper_signed_pdf_path VARCHAR(500) NULL;

COMMENT ON COLUMN rgpd_signature_requests.paper_signed_pdf_path IS
    'Ruta pública (/uploads/...) del PDF firmado en papel subido por gestor/admin';