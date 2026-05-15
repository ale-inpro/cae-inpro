<?php declare(strict_types=1); ?>
<!-- Overlay: análisis documento complementario CAE -->
<div id="doc-analyze-overlay" style="display:none;" aria-hidden="true" aria-live="polite">
    <div class="ai-loading-card">
        <div class="ai-loading-icon">
            <i class="bi bi-file-earmark-text"></i>
        </div>
        <h5 class="ai-loading-title" id="doc-analyze-overlay-title">Analizando documento</h5>
        <div class="ai-loading-steps">
            <div class="ai-step doc-analyze-step" data-step="0"><span class="ai-step-dot"></span><span>Subiendo archivo</span></div>
            <div class="ai-step doc-analyze-step" data-step="1"><span class="ai-step-dot"></span><span>Extrayendo fechas y contenido</span></div>
            <div class="ai-step doc-analyze-step" data-step="2"><span class="ai-step-dot"></span><span>Validando para el CAE</span></div>
        </div>
        <p class="ai-loading-hint">No cierres esta ventana. Puede tardar unos segundos…</p>
    </div>
</div>
