<?php
declare(strict_types=1);

/** @var string|null $active */
$active = isset($active) ? (string)$active : null; // elections|results|profile
?>
<nav class="navbar navbar-expand-lg portal-nav py-2">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?=htmlspecialchars(app_url('/enterprise/elections.php'))?>">
            <i class="bi bi-check2-circle me-2"></i>Vote Enterprise
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#portalNav" aria-controls="portalNav" aria-expanded="false" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="portalNav">
            <div class="ms-auto d-flex flex-column flex-lg-row gap-2 align-items-lg-center mt-3 mt-lg-0">
                <a class="btn btn-outline-light btn-sm<?=$active==='elections'?' active':''?>" href="<?=htmlspecialchars(app_url('/enterprise/elections.php'))?>"><i class="bi bi-ui-checks-grid me-1"></i>Scrutins</a>
                <a class="btn btn-outline-light btn-sm<?=$active==='results'?' active':''?>" href="<?=htmlspecialchars(app_url('/enterprise/results.php'))?>"><i class="bi bi-bar-chart-line me-1"></i>Resultats</a>
                <a class="btn btn-outline-light btn-sm<?=$active==='profile'?' active':''?>" href="<?=htmlspecialchars(app_url('/enterprise/profile.php'))?>"><i class="bi bi-person-circle me-1"></i>Profil</a>

                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-light position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger d-none" data-live-notif-badge>0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0 live-notif-menu">
                        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                            <strong class="small mb-0">Notifications</strong>
                            <button class="btn btn-link btn-sm text-decoration-none p-0" type="button" data-live-notif-mark-all>Tout lire</button>
                        </div>
                        <div class="list-group list-group-flush" data-live-notif-list></div>
                        <div class="px-3 py-2 small text-muted" data-live-notif-empty>Aucune notification.</div>
                    </div>
                </div>

                <?php if (user_has_role('ADMIN') || user_has_role('SUPERADMIN') || user_has_role('SCRUTATEUR')): ?>
                    <a class="btn btn-info btn-sm" href="<?=htmlspecialchars(app_url('/enterprise/admin/dashboard.php'))?>"><i class="bi bi-speedometer2 me-1"></i>Admin</a>
                <?php endif; ?>

                <button class="btn btn-sm btn-outline-light" id="btn-theme-toggle" type="button" aria-pressed="false" aria-label="Basculer theme clair/sombre">
                    <i class="bi bi-moon-stars"></i>
                </button>
                <button class="btn btn-sm btn-outline-light" id="btn-contrast-toggle" type="button" aria-pressed="false" aria-label="Basculer contraste eleve">
                    <i class="bi bi-circle-half"></i>
                </button>

                <a class="btn btn-danger btn-sm" href="<?=htmlspecialchars(app_url('/enterprise/logout.php?csrf=' . urlencode(csrf_token())))?>"><i class="bi bi-box-arrow-right me-1"></i>Deconnexion</a>
            </div>
        </div>
    </div>
</nav>

