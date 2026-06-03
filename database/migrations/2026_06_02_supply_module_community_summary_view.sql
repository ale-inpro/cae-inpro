-- Vista de resumen por comunidad (para pantalla Comunidades)
CREATE OR REPLACE VIEW v_supply_community_summary AS
SELECT
    c.id AS community_id,
    c.name AS community_name,
    c.city AS community_city,

    COUNT(sc.id) FILTER (WHERE sc.status IN ('active', 'pending_renewal')) AS active_contracts_count,
    COUNT(sc.id) FILTER (
        WHERE sc.status IN ('active', 'pending_renewal')
          AND sc.end_date IS NOT NULL
          AND sc.end_date <= (CURRENT_DATE + INTERVAL '60 day')
    ) AS contracts_expiring_60d_count,

    COALESCE(SUM(sc.admin_fee_eur) FILTER (WHERE sc.status IN ('active', 'pending_renewal')), 0)::NUMERIC(12,2) AS monthly_admin_fee_total_eur
FROM communities c
LEFT JOIN supply_contracts sc
    ON sc.scope = 'community'
   AND sc.community_id = c.id
GROUP BY c.id, c.name, c.city;