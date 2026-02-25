<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Dashboard';
/** @var bool $includeChart */
$includeChart = !empty($includeChart);
$isSuperadmin = user_has_role('SUPERADMIN');
$hasVoterRole = user_has_role('VOTER');
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <?php include __DIR__ . '/partial/head.php'; ?>
</head>
<body class="layout-fixed">
    <a class="skip-link" href="#main">Aller au contenu</a>
    <div class="wrapper">
        <?php include __DIR__ . '/partial/sidebar.php'; ?>
        <div class="main">
            <?php include __DIR__ . '/partial/topbar.php'; ?>

            <main class="content" id="main" tabindex="-1">
                <div class="container-fluid p-0">
                    <h1 class="h3 mb-3">Dashboard</h1>
                    <?php if ($hasVoterRole): ?>
                        <div class="alert alert-primary d-flex justify-content-between align-items-center">
                            <span>Ce compte peut aussi participer aux scrutins (role VOTER).</span>
                            <a class="btn btn-sm btn-primary" href="<?=htmlspecialchars(app_url('/enterprise/elections.php'))?>">Aller au vote</a>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="card kpi-card">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Élections</div>
                                        <div class="kpi-value" id="ent-stat-elections">—</div>
                                    </div>
                                    <div class="kpi-icon" aria-hidden="true"><i class="bi bi-ui-checks-grid"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card kpi-card">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Électeurs (actifs)</div>
                                        <div class="kpi-value" id="ent-stat-voters">—</div>
                                    </div>
                                    <div class="kpi-icon" aria-hidden="true"><i class="bi bi-people"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card kpi-card">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Votes (aujourd’hui)</div>
                                        <div class="kpi-value" id="ent-stat-votes-today">—</div>
                                    </div>
                                    <div class="kpi-icon" aria-hidden="true"><i class="bi bi-check2-circle"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card kpi-card">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Scrutins ouverts</div>
                                        <div class="kpi-value" id="ent-stat-open">—</div>
                                    </div>
                                    <div class="kpi-icon" aria-hidden="true"><i class="bi bi-broadcast"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">Votes (14 derniers jours)</h5>
                                    <div class="text-muted small">Tendance</div>
                                </div>
                                <div class="card-body">
                                    <div style="height:320px">
                                        <canvas id="ent-chart-votes" aria-label="Graphique votes par jour" role="img"></canvas>
                                    </div>
                                    <div class="text-muted small mt-2">Astuce: active le contraste élevé via le bouton “Contraste”.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Scrutins récents</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table mb-0">
                                            <thead><tr><th>Titre</th><th>Statut</th><th>Début</th><th>Fin</th></tr></thead>
                                            <tbody id="ent-table-elections"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if ($isSuperadmin): ?>
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Derniers événements</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table mb-0">
                                                <thead><tr><th>Date</th><th>Action</th><th>Entité</th></tr></thead>
                                                <tbody id="ent-table-audit"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>

            <?php include __DIR__ . '/partial/footer.php'; ?>
        </div>
    </div>

    <?php include __DIR__ . '/partial/scripts.php'; ?>
</body>
</html>


