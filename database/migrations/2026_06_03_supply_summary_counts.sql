DROP VIEW IF EXISTS v_supply_community_summary CASCADE;

CREATE VIEW v_supply_community_summary AS
SELECT
    c.id AS community_id,
    c.name AS community_name,
    c.city AS community_city,
    c.address AS community_address,
    COUNT(sc.id) FILTER (WHERE sc.status <> 'draft') AS total_contracts_count,
    COUNT(sc.id) FILTER (WHERE sc.status = 'active') AS active_count,
    COUNT(sc.id) FILTER (WHERE sc.status = 'pending_renewal') AS upcoming_count,
    COUNT(sc.id) FILTER (WHERE sc.status IN ('expired', 'cancelled')) AS inactive_count,
    COALESCE(SUM(sc.admin_fee_eur) FILTER (WHERE sc.status IN ('active', 'pending_renewal')), 0)::NUMERIC(12,2) AS monthly_admin_fee_total_eur
FROM communities c
LEFT JOIN supply_contracts sc
    ON sc.scope = 'community' AND sc.community_id = c.id
GROUP BY c.id, c.name, c.city, c.address;