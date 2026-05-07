<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaeController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RiskReportController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\NotificationController;

return [
    'routes' => [
        ['method' => 'GET', 'path' => '/login', 'action' => [AuthController::class, 'showLogin']],
        ['method' => 'POST', 'path' => '/login', 'action' => [AuthController::class, 'login']],
        ['method' => 'POST', 'path' => '/logout', 'action' => [AuthController::class, 'logout']],

        ['method' => 'GET', 'path' => '/dashboard', 'action' => [DashboardController::class, 'index']],

        ['method' => 'GET', 'path' => '/gestor/dashboard', 'action' => [DashboardController::class, 'gestor']],
        ['method' => 'GET', 'path' => '/gestor/tecnicos', 'action' => [TechnicianController::class, 'index']],
        ['method' => 'GET', 'path' => '/gestor/tecnicos/{id}', 'action' => [TechnicianController::class, 'show']],
        ['method' => 'GET', 'path' => '/gestor/comunidades', 'action' => [CommunityController::class, 'index']],
        ['method' => 'GET', 'path' => '/gestor/comunidades/{id}', 'action' => [CommunityController::class, 'show']],
        ['method' => 'GET', 'path' => '/gestor/cae', 'action' => [CaeController::class, 'index']],

        ['method' => 'GET', 'path' => '/admin/dashboard', 'action' => [DashboardController::class, 'admin']],
        ['method' => 'GET', 'path' => '/admin/tecnicos', 'action' => [TechnicianController::class, 'index']],
        ['method' => 'GET', 'path' => '/admin/tecnicos/create', 'action' => [TechnicianController::class, 'create']],
        ['method' => 'POST', 'path' => '/admin/tecnicos', 'action' => [TechnicianController::class, 'store']],
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

        // Eliminar notificaciones
        ['method' => 'POST', 'path' => '/admin/notificaciones/{notifId}/delete', 'action' => [NotificationController::class, 'deleteOne']],
        ['method' => 'POST', 'path' => '/gestor/notificaciones/{notifId}/delete','action' => [NotificationController::class, 'deleteOne']],
        ['method' => 'POST', 'path' => '/admin/notificaciones/delete-all',       'action' => [NotificationController::class, 'deleteAll']],
        ['method' => 'POST', 'path' => '/gestor/notificaciones/delete-all',      'action' => [NotificationController::class, 'deleteAll']],
    ],
];
