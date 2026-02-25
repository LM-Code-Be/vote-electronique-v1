<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Émargement';
/** @var bool $includeDataTables */
$includeDataTables = !empty($includeDataTables);
/** @var bool $canEdit */
$canEdit = !empty($canEdit);
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
                            <h1 class="h3 mb-0">Émargement / Éligibilité</h1>
                            <div class="text-muted small">Snapshot (optionnel) des électeurs éligibles par scrutin.</div>
                        </div>
                        <div class="d-flex gap-2 align-items-center flex-wrap page-actions">
                            <select class="form-select" id="ent-vr-election" style="min-width:320px"></select>
                            <button class="btn btn-outline-secondary" id="ent-vr-export"><i class="bi bi-download me-1"></i>Exporter CSV</button>
                            <?php if ($canEdit): ?>
                                <button class="btn btn-outline-warning" id="ent-vr-clear"><i class="bi bi-x-circle me-1"></i>Effacer snapshot</button>
                                <button class="btn btn-primary" id="ent-vr-generate"><i class="bi bi-lightning-charge me-1"></i>Générer snapshot</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="alert alert-info d-flex justify-content-between align-items-center" id="ent-vr-banner" style="display:none;">
                        <div><i class="bi bi-info-circle me-2"></i><span id="ent-vr-banner-text"></span></div>
                        <button class="btn btn-sm btn-light" id="ent-vr-refresh"><i class="bi bi-arrow-clockwise me-1"></i>Rafraîchir</button>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="dt-ent-voter-roll" class="table table-bordered w-100">
                                <thead>
                                    <tr>
                                        <th>Username</th><th>Nom</th><th>Email</th><th>Departement</th><th>Statut</th><th>Éligible</th>
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






