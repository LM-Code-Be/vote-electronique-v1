<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Utilisateurs';
/** @var bool $includeDataTables */
$includeDataTables = !empty($includeDataTables);
$canManageRoles = user_has_role('SUPERADMIN');
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <?php include __DIR__ . '/partial/head.php'; ?>
</head>
<body class="layout-fixed" data-can-manage-roles="<?=$canManageRoles ? '1' : '0'?>">
    <a class="skip-link" href="#main">Aller au contenu</a>
    <div class="wrapper">
        <?php include __DIR__ . '/partial/sidebar.php'; ?>
        <div class="main">
            <?php include __DIR__ . '/partial/topbar.php'; ?>

            <main class="content" id="main" tabindex="-1">
                <div class="container-fluid p-0">
                    <div class="d-flex justify-content-between align-items-center mb-3 page-header">
                        <h1 class="h3 mb-0">Utilisateurs</h1>
                        <div class="d-flex gap-2 flex-wrap page-actions">
                            <input type="file" id="ent-file-users" accept=".csv" hidden>
                            <button class="btn btn-outline-primary" id="ent-btn-import-users"><i class="bi bi-upload me-1"></i>Importer CSV</button>
                            <button class="btn btn-outline-secondary" id="ent-btn-export-users"><i class="bi bi-download me-1"></i>Exporter CSV</button>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-user"><i class="bi bi-plus-circle me-1"></i>Nouveau</button>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="dt-ent-users" class="table table-bordered w-100">
                                <thead>
                                    <tr>
                                        <th>Username</th><th>Nom</th><th>Email</th><th>Departement</th><th>Type</th><th>Statut</th><th>Groupes</th><th>Rôles</th><th style="width:260px">Actions</th>
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

    <div class="modal fade" id="modal-user" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <form class="modal-content" id="form-user">
                <div class="modal-header">
                    <h5 class="modal-title">Utilisateur</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <input type="hidden" id="ent-user-id">
                    <div class="col-md-4"><label class="form-label">Username</label><input class="form-control" id="ent-user-username" required></div>
                    <div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" id="ent-user-email"></div>
                    <div class="col-md-4"><label class="form-label">Matricule</label><input class="form-control" id="ent-user-employee-id"></div>
                    <div class="col-md-6"><label class="form-label">Nom complet</label><input class="form-control" id="ent-user-full-name" required></div>
                    <div class="col-md-3"><label class="form-label">Téléphone</label><input class="form-control" id="ent-user-phone"></div>
                    <div class="col-md-3"><label class="form-label">Departement</label><input class="form-control" id="ent-user-departement"></div>
                    <div class="col-md-6"><label class="form-label">Mot de passe</label><input type="password" class="form-control" id="ent-user-password" placeholder="(min 8)"></div>
                    <div class="col-md-3">
                        <label class="form-label">Type utilisateur</label>
                        <select class="form-select" id="ent-user-type">
                            <option value="INTERNAL">INTERNAL</option>
                            <option value="EXTERNAL">EXTERNAL</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Statut</label>
                        <select class="form-select" id="ent-user-status">
                            <option value="ACTIVE">ACTIVE</option>
                            <option value="SUSPENDED">SUSPENDED</option>
                            <option value="DELETED">DELETED</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Rôles</label>
                        <select class="form-select" id="ent-user-roles" multiple <?=$canManageRoles ? '' : 'disabled'?>></select>
                        <?php if (!$canManageRoles): ?>
                            <div class="form-text">Gestion des rôles réservée au SUPERADMIN.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Groupes</label>
                        <select class="form-select" id="ent-user-groups" multiple></select>
                    </div>
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






