<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Résultats';
/** @var bool $includeChart */
$includeChart = !empty($includeChart);
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
                    <div class="d-flex justify-content-between align-items-center mb-3 page-header">
                        <h1 class="h3 mb-0">Résultats</h1>
                        <div class="d-flex gap-2 flex-wrap page-actions">
                            <select class="form-select" id="ent-results-election" style="min-width:320px"></select>
                            <button class="btn btn-outline-primary" id="ent-btn-export-results"><i class="bi bi-download me-1"></i>Exporter CSV</button>
                            <button class="btn btn-outline-secondary" id="ent-btn-print-report"><i class="bi bi-printer me-1"></i>Imprimer (PDF)</button>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-7">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title mb-0">Dépouillement</h5></div>
                                <div class="card-body">
                                    <div id="ent-results-table"></div>
                                    <div class="mt-3" style="height:320px">
                                        <canvas id="ent-results-chart" aria-label="Graphique des résultats" role="img"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card">
                                <div class="card-header"><h5 class="card-title mb-0">Participation</h5></div>
                                <div class="card-body">
                                    <div class="row g-2">
                                        <div class="col-6"><div class="p-3 bg-light rounded"><div class="text-muted small">Éligibles</div><div class="h3 mb-0" id="ent-stat-eligible">—</div></div></div>
                                        <div class="col-6"><div class="p-3 bg-light rounded"><div class="text-muted small">A voté</div><div class="h3 mb-0" id="ent-stat-voted">—</div></div></div>
                                        <div class="col-12"><div class="p-3 bg-light rounded"><div class="text-muted small">Taux</div><div class="h3 mb-0" id="ent-stat-rate">—</div></div></div>
                                    </div>
                                    <div class="alert alert-info mt-3 mb-0">Par défaut, les résultats sont surtout pertinents après clôture.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <?php include __DIR__ . '/partial/footer.php'; ?>
        </div>
    </div>

    <?php include __DIR__ . '/partial/scripts.php'; ?>
</body>
</html>



