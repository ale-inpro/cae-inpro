<?php declare(strict_types=1); ?>
<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="card border-0 shadow-lg overflow-hidden">
            <div class="row g-0">
                <div class="col-md-5 p-4 text-white" style="background:linear-gradient(160deg,#10b981 0%,#047857 100%);">
                    <h2 class="h4 fw-bold mb-3">CAE Inpro</h2>
                    <p class="mb-0 opacity-75">Gestiona técnicos, comunidades y CAE de forma centralizada y eficiente.</p>
                </div>
                <div class="col-md-7 p-4 p-lg-5 bg-white">
                    <h1 class="h4 mb-1">Iniciar sesión</h1>
                    <p class="text-muted mb-4">Accede a tu panel de gestión</p>

                    <?php $bu = htmlspecialchars($baseUrl ?? '/cae-inpro/public'); ?>
                    <form method="post" action="<?= $bu ?>/login" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input name="email" type="email" class="form-control form-control-lg" placeholder="admin@inpro.local" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <input name="password" type="password" class="form-control form-control-lg" placeholder="••••••••" required>
                        </div>
                        <button class="btn btn-success btn-lg w-100" type="submit">Entrar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>