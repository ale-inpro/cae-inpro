<?php

declare(strict_types=1);

namespace App\Services\Rgpd;

use PDO;

/**
 * Estado de cumplimiento por vecino + plantilla.
 *
 * Prioridad: pending > signed > none
 * - pending: solicitud activa abierta (renovación o primera firma en curso)
 * - signed: firma vigente (signed/paper) sin solicitud pending
 * - none: sin firma vigente ni solicitud activa
 */
final class RgpdTemplateCompliance
{
    public const STATE_PENDING = 'pending';
    public const STATE_SIGNED = 'signed';
    public const STATE_NONE = 'none';

    public static function audienceSqlFragment(?string $audience): string
    {
        return match ($audience) {
            'owners' => ' AND r.is_owner = TRUE',
            'presidents' => ' AND r.is_president = TRUE',
            default => '',
        };
    }

    public static function audienceLabel(?string $audience): string
    {
        return match ($audience) {
            'owners' => 'Propietarios',
            'presidents' => 'Presidentes',
            'both', null => 'Todos los vecinos activos',
            default => 'Todos los vecinos activos',
        };
    }

    public static function countEligibleResidents(PDO $pdo, int $communityId, ?string $audience): int
    {
        $audWhere = self::audienceSqlFragment($audience);
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM community_residents r
            WHERE r.community_id = :cid AND r.is_active = TRUE {$audWhere}
        ");
        $stmt->execute(['cid' => $communityId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Estado actual de un vecino para una plantilla concreta.
     */
    public static function residentTemplateState(PDO $pdo, int $residentId, int $templateId): string
    {
        if ($residentId <= 0 || $templateId <= 0) {
            return self::STATE_NONE;
        }

        $stmt = $pdo->prepare("
            SELECT
                BOOL_OR(status = 'pending') AS has_pending,
                BOOL_OR(status IN ('signed', 'paper')) AS has_signed
            FROM rgpd_signature_requests
            WHERE resident_id = :rid AND template_id = :tid
        ");
        $stmt->execute(['rid' => $residentId, 'tid' => $templateId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if (!empty($row['has_pending'])) {
            return self::STATE_PENDING;
        }
        if (!empty($row['has_signed'])) {
            return self::STATE_SIGNED;
        }

        return self::STATE_NONE;
    }

    /**
     * Envío masivo estándar: omitir si ya hay firma vigente o solicitud activa.
     * (Renovación explícita usará otro modo en una fase posterior.)
     */
    public static function shouldSkipStandardSend(PDO $pdo, int $residentId, int $templateId): bool
    {
        $state = self::residentTemplateState($pdo, $residentId, $templateId);

        return $state === self::STATE_PENDING || $state === self::STATE_SIGNED;
    }

    public static function hasVigentSignature(PDO $pdo, int $residentId, int $templateId): bool
    {
        return self::residentTemplateState($pdo, $residentId, $templateId) === self::STATE_SIGNED;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function loadDocumentSummaries(PDO $pdo, int $communityId): array
    {
        $templates = $pdo->query("
            SELECT id, name, kind FROM rgpd_templates
            WHERE is_active = TRUE
            ORDER BY kind DESC, name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $lastCampStmt = $pdo->prepare("
            SELECT cp.id, cp.audience, cp.completed_at
            FROM rgpd_campaigns cp
            INNER JOIN rgpd_campaign_templates rct ON rct.campaign_id = cp.id
            WHERE cp.community_id = :cid
                AND cp.status = 'completed'
                AND rct.template_id = :tid
            ORDER BY cp.completed_at DESC NULLS LAST, cp.id DESC
            LIMIT 1
        ");

        $rows = [];
        foreach ($templates as $tpl) {
            $tid = (int) $tpl['id'];
            $lastCampStmt->execute(['cid' => $communityId, 'tid' => $tid]);
            $camp = $lastCampStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $audience = $camp ? (string) ($camp['audience'] ?? 'both') : null;
            $eligible = $camp !== null ? self::countEligibleResidents($pdo, $communityId, $audience) : 0;

            $breakdown = $camp !== null
                ? self::loadTemplateAudienceBreakdown($pdo, $communityId, $tid, $audience)
                : [
                    'signed' => 0,
                    'pending_n' => 0,
                    'unsent_n' => 0,
                    'outstanding' => 0,
                    'pending_residents' => [],
                    'unsent_residents' => [],
                ];

            $rows[] = [
                'template_id' => $tid,
                'template_name' => (string) $tpl['name'],
                'kind' => (string) $tpl['kind'],
                'has_campaign' => $camp !== null,
                'last_campaign_at' => $camp['completed_at'] ?? null,
                'audience' => $audience,
                'audience_label' => self::audienceLabel($audience),
                'eligible' => $eligible,
                'signed' => $breakdown['signed'],
                'pending_n' => $breakdown['pending_n'],
                'unsent_n' => $breakdown['unsent_n'],
                'outstanding' => $breakdown['outstanding'],
                'is_complete' => $camp !== null && $eligible > 0 && $breakdown['outstanding'] === 0,
                'pending_residents' => $breakdown['pending_residents'],
                'unsent_residents' => $breakdown['unsent_residents'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{signed_n: int, pending_n: int}>
     */
    public static function loadResidentStats(PDO $pdo, int $communityId): array
    {
        $stmt = $pdo->prepare("
            WITH template_states AS (
                SELECT
                    r.id AS resident_id,
                    t.id AS template_id,
                    CASE
                        WHEN EXISTS (
                            SELECT 1 FROM rgpd_signature_requests s_p
                            WHERE s_p.resident_id = r.id AND s_p.template_id = t.id
                                AND s_p.status = 'pending'
                        ) THEN '" . self::STATE_PENDING . "'
                        WHEN EXISTS (
                            SELECT 1 FROM rgpd_signature_requests s_f
                            WHERE s_f.resident_id = r.id AND s_f.template_id = t.id
                                AND s_f.status IN ('signed', 'paper')
                        ) THEN '" . self::STATE_SIGNED . "'
                        ELSE '" . self::STATE_NONE . "'
                    END AS compliance_state
                FROM community_residents r
                CROSS JOIN rgpd_templates t
                WHERE r.community_id = :cid AND r.is_active = TRUE AND t.is_active = TRUE
            )
            SELECT resident_id,
                COUNT(*) FILTER (WHERE compliance_state = '" . self::STATE_SIGNED . "')::int AS signed_n,
                COUNT(*) FILTER (WHERE compliance_state = '" . self::STATE_PENDING . "')::int AS pending_n
            FROM template_states
            GROUP BY resident_id
        ");
        $stmt->execute(['cid' => $communityId]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['resident_id']] = [
                'signed_n' => (int) ($row['signed_n'] ?? 0),
                'pending_n' => (int) ($row['pending_n'] ?? 0),
            ];
        }

        return $map;
    }

    /**
     * @return array{
     *   signed: int,
     *   pending_n: int,
     *   unsent_n: int,
     *   outstanding: int,
     *   pending_residents: list<array<string, mixed>>,
     *   unsent_residents: list<array<string, mixed>>
     * }
     */
    private static function loadTemplateAudienceBreakdown(
        PDO $pdo,
        int $communityId,
        int $templateId,
        ?string $audience
    ): array {
        $audWhere = self::audienceSqlFragment($audience);
        $stateSql = self::complianceStateSql('s', 'r');

        $stmt = $pdo->prepare("
            SELECT r.id,
                TRIM(CONCAT_WS(' ', r.nombre, r.apellidos)) AS resident_name,
                r.email,
                {$stateSql} AS compliance_state
            FROM community_residents r
            WHERE r.community_id = :cid AND r.is_active = TRUE
            {$audWhere}
            ORDER BY r.nombre, r.apellidos
        ");
        $stmt->execute(['cid' => $communityId, 'tid' => $templateId]);

        $signed = 0;
        $pendingResidents = [];
        $unsentResidents = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $state = (string) ($row['compliance_state'] ?? self::STATE_NONE);
            if ($state === self::STATE_SIGNED) {
                $signed++;
            } elseif ($state === self::STATE_PENDING) {
                $pendingResidents[] = [
                    'id' => (int) $row['id'],
                    'resident_name' => (string) ($row['resident_name'] ?? ''),
                    'email' => (string) ($row['email'] ?? ''),
                ];
            } else {
                $unsentResidents[] = [
                    'id' => (int) $row['id'],
                    'resident_name' => (string) ($row['resident_name'] ?? ''),
                    'email' => (string) ($row['email'] ?? ''),
                ];
            }
        }

        $pendingN = count($pendingResidents);
        $unsentN = count($unsentResidents);

        return [
            'signed' => $signed,
            'pending_n' => $pendingN,
            'unsent_n' => $unsentN,
            'outstanding' => $pendingN + $unsentN,
            'pending_residents' => $pendingResidents,
            'unsent_residents' => $unsentResidents,
        ];
    }

    /**
     * SQL inline para CASE compliance_state (pending > signed > none).
     *
     * @param string $sigAlias alias de rgpd_signature_requests en subconsultas (no usado, reservado)
     * @param string $resAlias alias del vecino (r)
     */
    private static function complianceStateSql(string $sigAlias, string $resAlias): string
    {
        unset($sigAlias);

        return "(CASE
            WHEN EXISTS (
                SELECT 1 FROM rgpd_signature_requests s_p
                WHERE s_p.resident_id = {$resAlias}.id AND s_p.template_id = :tid
                    AND s_p.status = 'pending'
            ) THEN '" . self::STATE_PENDING . "'
            WHEN EXISTS (
                SELECT 1 FROM rgpd_signature_requests s_f
                WHERE s_f.resident_id = {$resAlias}.id AND s_f.template_id = :tid
                    AND s_f.status IN ('signed', 'paper')
            ) THEN '" . self::STATE_SIGNED . "'
            ELSE '" . self::STATE_NONE . "'
        END)";
    }

    public const SEND_SKIP = 'skip';
    public const SEND_INVITE = 'invite';
    public const SEND_REMINDER = 'reminder';

    public static function hasCancelledRequest(PDO $pdo, int $residentId, int $templateId): bool
    {
        $stmt = $pdo->prepare("
            SELECT 1 FROM rgpd_signature_requests
            WHERE resident_id = :rid AND template_id = :tid AND status = 'cancelled'
            LIMIT 1
        ");
        $stmt->execute(['rid' => $residentId, 'tid' => $templateId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Acción de envío masivo para un vecino + plantilla.
     */
    public static function resolveMassSendAction(PDO $pdo, int $residentId, int $templateId): string
    {
        $state = self::residentTemplateState($pdo, $residentId, $templateId);
        if ($state === self::STATE_SIGNED) {
            return self::SEND_SKIP;
        }
        if ($state === self::STATE_PENDING) {
            return self::SEND_REMINDER;
        }

        return self::SEND_INVITE;
    }

    public static function cancelPendingRequests(PDO $pdo, int $residentId, int $templateId): void
    {
        $pdo->prepare("
            UPDATE rgpd_signature_requests
            SET status = 'cancelled', updated_at = NOW()
            WHERE resident_id = :rid AND template_id = :tid AND status = 'pending'
        ")->execute(['rid' => $residentId, 'tid' => $templateId]);
    }

    /**
     * Resumen del vecino para el wizard (varias plantillas).
     *
     * @param list<int> $templateIds
     * @return array{
     *   filter_state: string,
     *   selectable: bool,
     *   signed_n: int,
     *   pending_n: int,
     *   unsent_n: int,
     *   cancelled_n: int
     * }
     */
    public static function residentWizardSummary(PDO $pdo, int $residentId, array $templateIds): array
    {
        $signedN = 0;
        $pendingN = 0;
        $unsentN = 0;
        $cancelledN = 0;

        foreach ($templateIds as $tid) {
            $tid = (int) $tid;
            if ($tid <= 0) {
                continue;
            }
            $state = self::residentTemplateState($pdo, $residentId, $tid);
            if ($state === self::STATE_SIGNED) {
                $signedN++;
            } elseif ($state === self::STATE_PENDING) {
                $pendingN++;
            } else {
                $unsentN++;
                if (self::hasCancelledRequest($pdo, $residentId, $tid)) {
                    $cancelledN++;
                }
            }
        }

        $total = count(array_filter($templateIds, static fn($v) => (int) $v > 0));
        $selectable = ($signedN < $total);

        if ($pendingN > 0) {
            $filterState = self::STATE_PENDING;
        } elseif ($cancelledN > 0 && $unsentN === $cancelledN) {
            $filterState = 'cancelled';
        } elseif ($unsentN > 0) {
            $filterState = self::STATE_NONE;
        } else {
            $filterState = self::STATE_SIGNED;
        }

        return [
            'filter_state' => $filterState,
            'selectable' => $selectable,
            'signed_n' => $signedN,
            'pending_n' => $pendingN,
            'unsent_n' => $unsentN,
            'cancelled_n' => $cancelledN,
        ];
    }

    /**
     * @param list<int> $templateIds
     * @return list<array<string, mixed>>
     */
    public static function loadWizardResidents(PDO $pdo, int $communityId, array $templateIds): array
    {
        $templateIds = array_values(array_unique(array_filter(array_map('intval', $templateIds), static fn(int $v): bool => $v > 0)));
        if ($templateIds === []) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT r.id, r.nombre, r.apellidos, r.full_name, r.email,
                r.is_president, r.is_owner
            FROM community_residents r
            WHERE r.community_id = :cid AND r.is_active = TRUE
            ORDER BY r.is_president DESC, r.nombre, r.apellidos
        ");
        $stmt->execute(['cid' => $communityId]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rid = (int) ($r['id'] ?? 0);
            $summary = self::residentWizardSummary($pdo, $rid, $templateIds);
            $templateTotal = count($templateIds);
            $rows[] = array_merge($r, [
                'resident_name' => trim((string) (($r['nombre'] ?? '') . ' ' . ($r['apellidos'] ?? ''))),
                'filter_state' => $summary['filter_state'],
                'selectable' => $summary['selectable'],
                'signed_n' => $summary['signed_n'],
                'pending_n' => $summary['pending_n'],
                'unsent_n' => $summary['unsent_n'],
                'cancelled_n' => $summary['cancelled_n'],
                'template_total' => $templateTotal,
                'is_fully_signed' => $summary['signed_n'] === $templateTotal,
                'status_badges' => self::buildWizardStatusBadges(
                    $summary['signed_n'],
                    $summary['pending_n'],
                    $summary['unsent_n'],
                    $summary['cancelled_n'],
                    $templateTotal
                ),
            ]);
        }

        return $rows;
    }

        /**
     * Etiquetas de estado para cards del wizard (multi-plantilla).
     *
     * @return list<array{class: string, label: string}>
     */
    public static function buildWizardStatusBadges(int $signedN, int $pendingN, int $unsentN, int $cancelledN, int $total): array
    {
        if ($total <= 0) {
            return [];
        }

        if ($signedN === $total) {
            return [['class' => 'rgpd-wizard-badge rgpd-wizard-badge-signed', 'label' => 'Todas firmadas']];
        }
        if ($pendingN === $total) {
            return [['class' => 'rgpd-wizard-badge rgpd-wizard-badge-pending', 'label' => 'Todas pendientes']];
        }
        if ($cancelledN === $total) {
            return [['class' => 'rgpd-wizard-badge rgpd-wizard-badge-cancelled', 'label' => 'Todas canceladas']];
        }
        if ($unsentN === $total && $cancelledN === 0) {
            return [['class' => 'rgpd-wizard-badge rgpd-wizard-badge-none', 'label' => 'Todas sin enviar']];
        }

        $badges = [];
        $freshUnsent = max(0, $unsentN - $cancelledN);

        if ($pendingN > 0) {
            $badges[] = ['class' => 'rgpd-wizard-badge rgpd-wizard-badge-pending', 'label' => $pendingN . ($pendingN === 1 ? ' pendiente' : ' pendientes')];
        }
        if ($signedN > 0) {
            $badges[] = ['class' => 'rgpd-wizard-badge rgpd-wizard-badge-signed', 'label' => $signedN . ($signedN === 1 ? ' firmada' : ' firmadas')];
        }
        if ($freshUnsent > 0) {
            $badges[] = ['class' => 'rgpd-wizard-badge rgpd-wizard-badge-none', 'label' => $freshUnsent . ($freshUnsent === 1 ? ' sin enviar' : ' sin enviar')];
        }
        if ($cancelledN > 0) {
            $badges[] = ['class' => 'rgpd-wizard-badge rgpd-wizard-badge-cancelled', 'label' => $cancelledN . ($cancelledN === 1 ? ' cancelada' : ' canceladas')];
        }

        return $badges;
    }

        /**
     * Plantillas que el vecino aún puede firmar en papel (no tiene signed/paper vigente).
     *
     * @return list<array{id: int, name: string, state: string}>
     */
    public static function paperUploadableTemplates(PDO $pdo, int $residentId): array
    {
        $tplStmt = $pdo->query("
            SELECT id, name FROM rgpd_templates WHERE is_active = TRUE ORDER BY name
        ");
        $out = [];
        foreach ($tplStmt->fetchAll(PDO::FETCH_ASSOC) as $tpl) {
            $tid = (int) ($tpl['id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $state = self::residentTemplateState($pdo, $residentId, $tid);
            if ($state === self::STATE_SIGNED) {
                continue;
            }
            $out[] = [
                'id' => $tid,
                'name' => (string) ($tpl['name'] ?? ''),
                'state' => $state,
            ];
        }

        return $out;
    }

    /**
     * Vecinos activos de la comunidad sin firma vigente (signed/paper) para esa plantilla.
     *
     * @return list<array<string, mixed>>
     */
    public static function blankDownloadableResidents(PDO $pdo, int $communityId, int $templateId): array
    {
        $stateSql = self::complianceStateSql('s', 'r');
        $stmt = $pdo->prepare("
            SELECT r.id,
                TRIM(CONCAT_WS(' ', r.nombre, r.apellidos)) AS resident_name,
                r.email,
                {$stateSql} AS compliance_state
            FROM community_residents r
            WHERE r.community_id = :cid AND r.is_active = TRUE
            ORDER BY r.nombre, r.apellidos
        ");
        $stmt->execute(['cid' => $communityId, 'tid' => $templateId]);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((string) ($row['compliance_state'] ?? self::STATE_NONE) === self::STATE_SIGNED) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }
}
