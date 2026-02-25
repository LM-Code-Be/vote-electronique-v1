<nav class="navbar navbar-expand navbar-light navbar-bg px-3">
    <a class="nav-link js-sidebar-toggle p-2 me-2" href="#" aria-label="Menu latéral">
        <i class="bi bi-list fs-4"></i>
    </a>
    <div class="navbar-collapse collapse">
        <ul class="navbar-nav ms-auto align-items-center gap-1">
            <li class="nav-item me-2 d-none d-md-inline text-muted small">
                <i class="bi bi-person-circle me-1"></i><?=htmlspecialchars(user_current_username() ?? 'Utilisateur')?>
            </li>
            <li class="nav-item dropdown">
                <button class="btn btn-sm btn-outline-secondary position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
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
            </li>
            <li class="nav-item">
                <?php if (user_has_role('VOTER')): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?=htmlspecialchars(app_url('/enterprise/elections.php'))?>" title="Portail votant" aria-label="Portail votant">
                        <i class="bi bi-check2-square me-1"></i>Voter
                    </a>
                <?php else: ?>
                    <span class="badge text-bg-secondary" title="Ce compte ne possede pas le role VOTER">Sans role VOTER</span>
                <?php endif; ?>
            </li>
            <li class="nav-item">
                <button class="btn btn-sm btn-outline-secondary" id="btn-theme-toggle" type="button" aria-pressed="false" aria-label="Basculer thème clair/sombre">
                    <i class="bi bi-moon-stars"></i>
                </button>
            </li>
            <li class="nav-item">
                <button class="btn btn-sm btn-outline-secondary" id="btn-contrast-toggle" type="button" aria-pressed="false" aria-label="Basculer contraste élevé">
                    <i class="bi bi-circle-half"></i>
                </button>
            </li>
            <li class="nav-item">
                <a class="btn btn-sm btn-outline-danger" href="<?=htmlspecialchars(app_url('/enterprise/logout.php?csrf=' . urlencode(csrf_token())))?>" title="Déconnexion" aria-label="Déconnexion">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </li>
        </ul>
    </div>
</nav>
