<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaeController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RiskReportController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CaeAiController;
use App\Http\Controllers\TechnicianPortalController;
use App\Http\Controllers\AeatCotejoTestController;
use App\Http\Controllers\TechnicianAssociationController;

use App\Http\Controllers\Rgpd\RgpdDashboardController;
use App\Http\Controllers\Rgpd\RgpdTemplateController;
use App\Http\Controllers\Rgpd\RgpdCommunityController;
use App\Http\Controllers\Rgpd\RgpdContractController;
use App\Http\Controllers\Rgpd\RgpdMassSendController;
use App\Http\Controllers\Rgpd\RgpdSignController;

return [
    'routes' => [
        ['method' => 'GET', 'path' => '/login', 'action' => [AuthController::class, 'showLogin']],
        ['method' => 'POST', 'path' => '/login', 'action' => [AuthController::class, 'login']],
        ['method' => 'POST', 'path' => '/logout', 'action' => [AuthController::class, 'logout']],

        ['method' => 'GET', 'path' => '/dashboard', 'action' => [DashboardController::class, 'index']],

        ['method' => 'GET', 'path' => '/gestor/dashboard', 'action' => [DashboardController::class, 'gestor']],
        ['method' => 'GET', 'path' => '/gestor/tecnicos', 'action' => [TechnicianController::class, 'index']],
        ['method' => 'GET',  'path' => '/gestor/tecnicos/vincular', 'action' => [TechnicianController::class, 'gestorLinkForm']],
        ['method' => 'POST', 'path' => '/gestor/tecnicos/vincular', 'action' => [TechnicianController::class, 'gestorLinkLookup']],
        ['method' => 'GET',  'path' => '/gestor/tecnicos/nuevo', 'action' => [TechnicianController::class, 'gestorCreate']],
        ['method' => 'POST', 'path' => '/gestor/tecnicos/nuevo', 'action' => [TechnicianController::class, 'gestorStore']],
        ['method' => 'POST', 'path' => '/gestor/tecnicos/solicitar-asociacion', 'action' => [TechnicianController::class, 'gestorRequestAssociation']],
        ['method' => 'GET', 'path' => '/gestor/tecnicos/{id}', 'action' => [TechnicianController::class, 'show']],
        ['method' => 'GET', 'path' => '/gestor/comunidades', 'action' => [CommunityController::class, 'index']],
        ['method' => 'GET', 'path' => '/gestor/comunidades/{id}', 'action' => [CommunityController::class, 'show']],
        ['method' => 'GET', 'path' => '/gestor/cae', 'action' => [CaeController::class, 'index']],

        ['method' => 'GET', 'path' => '/admin/dashboard', 'action' => [DashboardController::class, 'admin']],
        ['method' => 'GET', 'path' => '/admin/tecnicos', 'action' => [TechnicianController::class, 'index']],
        ['method' => 'GET', 'path' => '/admin/tecnicos/create', 'action' => [TechnicianController::class, 'create']],
        ['method' => 'POST', 'path' => '/admin/tecnicos', 'action' => [TechnicianController::class, 'store']],
        ['method' => 'GET',  'path' => '/admin/tecnicos/solicitudes', 'action' => [TechnicianAssociationController::class, 'index']],
        ['method' => 'POST', 'path' => '/admin/tecnicos/solicitudes/{id}/aprobar', 'action' => [TechnicianAssociationController::class, 'approve']],
        ['method' => 'POST', 'path' => '/admin/tecnicos/solicitudes/{id}/rechazar', 'action' => [TechnicianAssociationController::class, 'reject']],
        ['method' => 'GET',  'path' => '/admin/tecnicos/association-sync', 'action' => [TechnicianAssociationController::class, 'syncPoll']],
        ['method' => 'GET', 'path' => '/admin/tecnicos/{id}', 'action' => [TechnicianController::class, 'show']],
        ['method' => 'GET', 'path' => '/admin/tecnicos/{id}/edit', 'action' => [TechnicianController::class, 'edit']],
        ['method' => 'PUT', 'path' => '/admin/tecnicos/{id}', 'action' => [TechnicianController::class, 'update']],
        ['method' => 'DELETE', 'path' => '/admin/tecnicos/{id}', 'action' => [TechnicianController::class, 'destroy']],
        ['method' => 'GET', 'path' => '/admin/tecnicos/{id}/cae', 'action' => [CaeController::class, 'history']],
        ['method' => 'POST', 'path' => '/admin/tecnicos/{id}/cae', 'action' => [CaeController::class, 'store']],
        ['method' => 'POST', 'path' => '/admin/tecnicos/{id}/cae/request-docs', 'action' => [CaeController::class, 'requestDocuments']],
        ['method' => 'PUT', 'path' => '/admin/cae/{id}', 'action' => [CaeController::class, 'update']],
        ['method' => 'DELETE', 'path' => '/admin/cae/{id}', 'action' => [CaeController::class, 'destroy']],
        ['method' => 'POST', 'path' => '/admin/cae/{id}/documentos', 'action' => [CaeController::class, 'uploadDocument']],
        ['method' => 'POST', 'path' => '/admin/cae/{id}/documentos/hacienda-csv', 'action' => [CaeController::class, 'fetchHaciendaByCsv']],
        ['method' => 'DELETE', 'path' => '/admin/cae/documentos/{documentId}', 'action' => [CaeController::class, 'deleteDocument']],

        ['method' => 'GET', 'path' => '/admin/comunidades', 'action' => [CommunityController::class, 'index']],
        ['method' => 'GET', 'path' => '/admin/comunidades/create', 'action' => [CommunityController::class, 'create']],
        ['method' => 'POST', 'path' => '/admin/comunidades', 'action' => [CommunityController::class, 'store']],
        ['method' => 'GET', 'path' => '/admin/comunidades/{id}', 'action' => [CommunityController::class, 'show']],
        ['method' => 'GET', 'path' => '/admin/comunidades/{id}/edit', 'action' => [CommunityController::class, 'edit']],
        ['method' => 'PUT', 'path' => '/admin/comunidades/{id}', 'action' => [CommunityController::class, 'update']],
        ['method' => 'DELETE', 'path' => '/admin/comunidades/{id}', 'action' => [CommunityController::class, 'destroy']],
        ['method' => 'POST', 'path' => '/admin/comunidades/{id}/tecnicos/{techId}', 'action' => [CommunityController::class, 'assignTechnician']],
        ['method' => 'DELETE', 'path' => '/admin/comunidades/{id}/tecnicos/{techId}', 'action' => [CommunityController::class, 'unassignTechnician']],
        ['method' => 'POST', 'path' => '/admin/comunidades/{id}/documentos', 'action' => [CommunityController::class, 'uploadDocument']],
        ['method' => 'DELETE', 'path' => '/admin/comunidades/documentos/{docId}', 'action' => [CommunityController::class, 'deleteDocument']],
        ['method' => 'GET', 'path' => '/admin/comunidades/{id}/riesgos', 'action' => [RiskReportController::class, 'show']],
        ['method' => 'PUT', 'path' => '/admin/comunidades/{id}/riesgos', 'action' => [RiskReportController::class, 'updateStatus']],
        ['method' => 'POST', 'path' => '/admin/comunidades/{id}/riesgos/upload', 'action' => [RiskReportController::class, 'uploadReport']],
        ['method' => 'GET', 'path' => '/admin/comunidades/{id}/riesgos/download', 'action' => [RiskReportController::class, 'downloadReport']],
        ['method' => 'GET', 'path' => '/admin/cae', 'action' => [CaeController::class, 'index']],

        ['method' => 'GET', 'path' => '/gestor/cae/documentos/{documentId}/download', 'action' => [CaeController::class, 'downloadDocument']],
        ['method' => 'GET', 'path' => '/admin/cae/documentos/{documentId}/download', 'action' => [CaeController::class, 'downloadDocument']],
        ['method' => 'GET', 'path' => '/gestor/comunidades/documentos/{docId}/download', 'action' => [CommunityController::class, 'downloadDocument']],
        ['method' => 'GET', 'path' => '/admin/comunidades/documentos/{docId}/download', 'action' => [CommunityController::class, 'downloadDocument']],
        ['method' => 'GET', 'path' => '/gestor/comunidades/{id}/riesgos/download', 'action' => [RiskReportController::class, 'downloadReport']],
        ['method' => 'POST', 'path' => '/gestor/comunidades/{id}/tecnicos/{techId}/feedback', 'action' => [CommunityController::class, 'saveTechnicianFeedback']],
        ['method' => 'DELETE', 'path' => '/admin/comunidades/{id}/riesgos', 'action' => [RiskReportController::class, 'deleteReport']],
        ['method' => 'POST', 'path' => '/admin/comunidades/{id}/riesgos/requests/{requestId}/reject', 'action' => [RiskReportController::class, 'rejectRequest']],
    
        // Notificaciones (admin)
        ['method' => 'GET',  'path' => '/admin/notificaciones',          'action' => [NotificationController::class, 'index']],
        ['method' => 'POST', 'path' => '/admin/notificaciones/read-all', 'action' => [NotificationController::class, 'markAllRead']],

        // Notificaciones (gestor)
        ['method' => 'GET',  'path' => '/gestor/notificaciones',          'action' => [NotificationController::class, 'index']],
        ['method' => 'POST', 'path' => '/gestor/notificaciones/read-all', 'action' => [NotificationController::class, 'markAllRead']],

        // Polling de notificaciones (endpoint JSON)
        ['method' => 'GET',  'path' => '/admin/notificaciones/poll',             'action' => [NotificationController::class, 'pollData']],
        ['method' => 'GET',  'path' => '/gestor/notificaciones/poll',            'action' => [NotificationController::class, 'pollData']],

        // Marcar una notificación como leída
        ['method' => 'POST', 'path' => '/admin/notificaciones/{notifId}/read',   'action' => [NotificationController::class, 'markOneRead']],
        ['method' => 'POST', 'path' => '/gestor/notificaciones/{notifId}/read',  'action' => [NotificationController::class, 'markOneRead']],

        // Abrir notificación: marca como leída y redirige a su destino (solo admin)
        ['method' => 'GET', 'path' => '/admin/notificaciones/{notifId}/open', 'action' => [NotificationController::class, 'openNotification']],

        // Solicitud de informe RL por el gestor
        ['method' => 'POST', 'path' => '/gestor/comunidades/{id}/riesgos/request', 'action' => [RiskReportController::class, 'requestFromGestor']],
        
        // Subida de informe RL por el gestor
        ['method' => 'POST', 'path' => '/gestor/comunidades/{id}/riesgos/upload', 'action' => [RiskReportController::class, 'uploadReport']],

        ['method' => 'GET', 'path' => '/gestor/notificaciones/{notifId}/open', 'action' => [NotificationController::class, 'openNotification']],

        // Eliminar notificaciones
        ['method' => 'POST', 'path' => '/admin/notificaciones/{notifId}/delete', 'action' => [NotificationController::class, 'deleteOne']],
        ['method' => 'POST', 'path' => '/gestor/notificaciones/{notifId}/delete','action' => [NotificationController::class, 'deleteOne']],
        ['method' => 'POST', 'path' => '/admin/notificaciones/delete-all',       'action' => [NotificationController::class, 'deleteAll']],
        ['method' => 'POST', 'path' => '/gestor/notificaciones/delete-all',      'action' => [NotificationController::class, 'deleteAll']],

        ['method' => 'POST', 'path' => '/admin/tecnicos/{id}/cae/ia/generate', 'action' => [CaeAiController::class, 'generate']],
        ['method' => 'GET',  'path' => '/admin/tecnicos/{id}/cae/ia/builder', 'action' => [CaeAiController::class, 'builder']],
        ['method' => 'POST', 'path' => '/admin/tecnicos/{id}/cae/ia/save',     'action' => [CaeAiController::class, 'save']],
        ['method' => 'GET',  'path' => '/admin/cae/ia/{generationId}/download', 'action' => [CaeAiController::class, 'download']],

        // --- RGPD (admin) ---
        ['method' => 'GET',  'path' => '/admin/rgpd', 'action' => [RgpdDashboardController::class, 'index']],
        ['method' => 'GET',  'path' => '/admin/rgpd/plantillas', 'action' => [RgpdTemplateController::class, 'index']],
        ['method' => 'GET',  'path' => '/admin/rgpd/plantillas/nueva', 'action' => [RgpdTemplateController::class, 'create']],
        ['method' => 'POST', 'path' => '/admin/rgpd/plantillas', 'action' => [RgpdTemplateController::class, 'store']],
        ['method' => 'GET',  'path' => '/admin/rgpd/plantillas/{id}', 'action' => [RgpdTemplateController::class, 'show']],
        ['method' => 'GET',  'path' => '/admin/rgpd/plantillas/{id}/editar', 'action' => [RgpdTemplateController::class, 'edit']],
        ['method' => 'PUT',  'path' => '/admin/rgpd/plantillas/{id}', 'action' => [RgpdTemplateController::class, 'update']],
        ['method' => 'DELETE', 'path' => '/admin/rgpd/plantillas/{id}', 'action' => [RgpdTemplateController::class, 'destroy']],
        ['method' => 'GET',  'path' => '/admin/rgpd/comunidades', 'action' => [RgpdCommunityController::class, 'index']],
        ['method' => 'GET',  'path' => '/admin/rgpd/comunidades/{id}', 'action' => [RgpdCommunityController::class, 'show']],
        ['method' => 'POST', 'path' => '/admin/rgpd/comunidades/{id}/presidente', 'action' => [RgpdCommunityController::class, 'assignPresident']],
        ['method' => 'DELETE', 'path' => '/admin/rgpd/comunidades/{id}/presidente', 'action' => [RgpdCommunityController::class, 'unassignPresident']],
        ['method' => 'GET',  'path' => '/admin/rgpd/contratos', 'action' => [RgpdContractController::class, 'index']],
        ['method' => 'POST', 'path' => '/admin/rgpd/contratos/{communityId}/papel', 'action' => [RgpdContractController::class, 'registerPaper']],
        ['method' => 'POST', 'path' => '/admin/rgpd/contratos/{communityId}/upload', 'action' => [RgpdContractController::class, 'uploadPdf']],
        ['method' => 'GET',  'path' => '/admin/rgpd/envio-masivo', 'action' => [RgpdMassSendController::class, 'wizard']],
        ['method' => 'POST', 'path' => '/admin/rgpd/envio-masivo', 'action' => [RgpdMassSendController::class, 'launch']],
        ['method' => 'POST', 'path' => '/admin/rgpd/firmas/{id}/reenviar', 'action' => [RgpdMassSendController::class, 'resend']],
        ['method' => 'POST', 'path' => '/admin/rgpd/firmas/{id}/papel', 'action' => [RgpdMassSendController::class, 'markPaper']],

        // --- RGPD (gestor) — mismas acciones, filtro por manager_company en controladores ---
        ['method' => 'GET',  'path' => '/gestor/rgpd', 'action' => [RgpdDashboardController::class, 'index']],
        ['method' => 'GET',  'path' => '/gestor/rgpd/plantillas', 'action' => [RgpdTemplateController::class, 'index']],
        ['method' => 'GET',  'path' => '/gestor/rgpd/comunidades', 'action' => [RgpdCommunityController::class, 'index']],
        ['method' => 'GET',  'path' => '/gestor/rgpd/comunidades/{id}', 'action' => [RgpdCommunityController::class, 'show']],
        ['method' => 'POST', 'path' => '/gestor/rgpd/comunidades/{id}/presidente', 'action' => [RgpdCommunityController::class, 'assignPresident']],
        ['method' => 'DELETE', 'path' => '/gestor/rgpd/comunidades/{id}/presidente', 'action' => [RgpdCommunityController::class, 'unassignPresident']],
        ['method' => 'GET',  'path' => '/gestor/rgpd/contratos', 'action' => [RgpdContractController::class, 'index']],
        ['method' => 'POST', 'path' => '/gestor/rgpd/contratos/{communityId}/papel', 'action' => [RgpdContractController::class, 'registerPaper']],
        ['method' => 'POST', 'path' => '/gestor/rgpd/contratos/{communityId}/upload', 'action' => [RgpdContractController::class, 'uploadPdf']],
        ['method' => 'GET',  'path' => '/gestor/rgpd/envio-masivo', 'action' => [RgpdMassSendController::class, 'wizard']],
        ['method' => 'POST', 'path' => '/gestor/rgpd/envio-masivo', 'action' => [RgpdMassSendController::class, 'launch']],
        ['method' => 'POST', 'path' => '/gestor/rgpd/firmas/{id}/reenviar', 'action' => [RgpdMassSendController::class, 'resend']],
        ['method' => 'POST', 'path' => '/gestor/rgpd/firmas/{id}/papel', 'action' => [RgpdMassSendController::class, 'markPaper']],

        // Firma pública (sin login)
        ['method' => 'GET',  'path' => '/rgpd/firmar/{token}', 'action' => [RgpdSignController::class, 'show']],
        ['method' => 'POST', 'path' => '/rgpd/firmar/{token}', 'action' => [RgpdSignController::class, 'submit']],

        // Portal público para técnicos (sin autenticación)
        ['method' => 'GET',  'path' => '/portal/{token}',        'action' => [TechnicianPortalController::class, 'show']],
        ['method' => 'POST', 'path' => '/portal/{token}/upload',  'action' => [TechnicianPortalController::class, 'upload']],
        ['method' => 'POST', 'path' => '/admin/cae/intake/{id}/approve', 'action' => [CaeController::class, 'approveIntake']],
        ['method' => 'POST', 'path' => '/admin/cae/intake/{id}/reject', 'action' => [CaeController::class, 'rejectIntake']],

        ['method' => 'POST', 'path' => '/admin/dev/aeat-cotejo-probe', 'action' => [AeatCotejoTestController::class, 'probe']],
        ['method' => 'GET',  'path' => '/admin/dev/aeat-cotejo-probe', 'action' => [AeatCotejoTestController::class, 'probe']],

        ['method' => 'POST', 'path' => '/admin/cae/documentos/{documentId}/verify-aeat', 'action' => [CaeController::class, 'verifyAeatDocument']],

        ['method' => 'GET', 'path' => '/admin/comunidades/{id}/sync', 'action' => [CommunityController::class, 'syncState']],
    ],
];
