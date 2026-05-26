INSERT INTO rgpd_templates (kind, name, slug, category, description, body_html, is_active)
VALUES
(
  'system',
  'Consentimiento Comunicaciones Electrónicas',
  'consentimiento-comunicaciones',
  'consentimiento',
  'Autorización para el envío de comunicaciones por medios electrónicos',
  '<h2>CONSENTIMIENTO PARA COMUNICACIONES ELECTRÓNICAS</h2>
<p>Mediante la presente, el/la abajo firmante autoriza a la comunidad de propietarios <strong>[COMUNIDAD]</strong>
a remitirle comunicaciones relacionadas con la gestión de la comunidad por correo electrónico, SMS u otros medios electrónicos.</p>
<p>Puede revocar este consentimiento en cualquier momento escribiendo a <strong>[EMAIL]</strong>.</p>',
  TRUE
),
(
  'system',
  'Consentimiento General RGPD',
  'consentimiento-general-rgpd',
  'consentimiento',
  'Consentimiento básico para el tratamiento de datos personales',
  '<h2>CONSENTIMIENTO PARA EL TRATAMIENTO DE DATOS PERSONALES</h2>
<p>De conformidad con el Reglamento (UE) 2016/679 (RGPD), consiento el tratamiento de mis datos personales
por la comunidad <strong>[COMUNIDAD]</strong> para la gestión ordinaria de la comunidad de propietarios.</p>
<p>Puede ejercer sus derechos de acceso, rectificación, supresión y demás escribiendo a <strong>[EMAIL]</strong>.</p>',
  TRUE
),
(
  'system',
  'Consentimiento Videovigilancia',
  'consentimiento-videovigilancia',
  'consentimiento',
  'Consentimiento para sistemas de videovigilancia en zonas comunes',
  '<h2>CONSENTIMIENTO PARA VIDEOVIGILANCIA</h2>
<p>Se informa de la existencia de sistemas de videovigilancia en zonas comunes de <strong>[COMUNIDAD]</strong>.
Las imágenes se conservarán como máximo 30 días, salvo incidencia.</p>
<p>Para ejercer sus derechos, contacte en <strong>[EMAIL]</strong>.</p>',
  TRUE
)
-- El índice uq_rgpd_templates_slug es parcial (WHERE slug IS NOT NULL); la cláusula debe coincidir.
ON CONFLICT (slug) WHERE slug IS NOT NULL DO NOTHING;