<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaeController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RiskReportController;
use App\Http\Controllers\TechnicianController;

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
    ],
];
