<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Participation';
/** @var bool $includeDataTables */
$includeDataTables = !empty($includeDataTables);
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
                        <div>
                            <h1 class="h3 mb-0">Participation</h1>
                            <div class="text-muted small">Suivi en temps reel des electeurs eligibles et votes.</div>
                        </div>
                        <div class="d-flex gap-2 align-items-center flex-wrap page-actions">
                            <select class="form-select" id="ent-part-election" style="min-width:320px"></select>
                            <div class="form-check ms-2">
                                <input class="form-check-input" type="checkbox" id="ent-part-missing">
                                <label class="form-check-label" for="ent-part-missing">Non votants</label>
                            </div>
                            <button class="btn btn-outline-secondary" id="ent-part-export"><i class="bi bi-download me-1"></i>Exporter CSV</button>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div class="d-flex gap-4 flex-wrap">
                                <div><div class="text-muted small">Eligibles</div><div class="h4 mb-0" id="ent-part-eligible">-</div></div>
                                <div><div class="text-muted small">Ont vote</div><div class="h4 mb-0" id="ent-part-voted">-</div></div>
                                <div><div class="text-muted small">Participation</div><div class="h4 mb-0" id="ent-part-rate">-</div></div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" id="ent-part-live" checked>
                                    <label class="form-check-label" for="ent-part-live">Live</label>
                                </div>
                                <select class="form-select form-select-sm" id="ent-part-interval" style="width:auto">
                                    <option value="5">5s</option>
                                    <option value="10" selected>10s</option>
                                    <option value="15">15s</option>
                                    <option value="30">30s</option>
                                </select>
                                <button class="btn btn-light" id="ent-part-refresh"><i class="bi bi-arrow-clockwise me-1"></i>Rafraichir</button>
                            </div>
                        </div>
                        <div class="card-footer small text-muted d-flex justify-content-between flex-wrap gap-2">
                            <span id="ent-part-last-sync">Derniere mise a jour: -</span>
                            <span id="ent-part-live-status">Live actif</span>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <h2 class="h6 mb-3">Derniers votes recus</h2>
                            <div id="ent-part-recent" class="small text-muted">Aucune donnee.</div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="dt-ent-participation" class="table table-bordered w-100">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Nom</th>
                                            <th>Email</th>
                                            <th>Departement</th>
                                            <th>Groupes</th>
                                            <th>Vote le</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
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

