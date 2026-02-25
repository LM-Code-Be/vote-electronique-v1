<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Groupes';
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
                        <h1 class="h3 mb-0">Departements</h1>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-group"><i class="bi bi-plus-circle me-1"></i>Nouveau</button>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="dt-ent-groups" class="table table-bordered w-100">
                                <thead><tr><th>Nom</th><th>Créé</th><th style="width:130px">Actions</th></tr></thead>
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

    <div class="modal fade" id="modal-group" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" id="form-group">
                <div class="modal-header">
                    <h5 class="modal-title">Departement</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <input type="hidden" id="ent-group-id">
                    <div class="col-12"><label class="form-label">Nom du departement</label><input class="form-control" id="ent-group-name" required></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/partial/scripts.php'; ?>
</body>
</html>






