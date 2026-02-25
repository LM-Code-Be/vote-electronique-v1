<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Notifications';
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
                        <h1 class="h3 mb-0">Notifications</h1>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-notif"><i class="bi bi-plus-circle me-1"></i>Nouvelle</button>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="dt-ent-notifs" class="table table-bordered w-100">
                                    <thead>
                                        <tr>
                                            <th>Titre</th>
                                            <th>Niveau</th>
                                            <th>Audience</th>
                                            <th>Push</th>
                                            <th>Active</th>
                                            <th>Lien</th>
                                            <th>Envois</th>
                                            <th>Debut</th>
                                            <th>Fin</th>
                                            <th style="width:130px">Actions</th>
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

    <div class="modal fade" id="modal-notif" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <form class="modal-content" id="form-notif">
                <div class="modal-header">
                    <h5 class="modal-title">Notification</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body row g-3">
                    <input type="hidden" id="ent-notif-id">
                    <div class="col-md-6">
                        <label class="form-label">Titre</label>
                        <input class="form-control" id="ent-notif-title" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Audience</label>
                        <select class="form-select" id="ent-notif-audience">
                            <option value="ALL">ALL</option>
                            <option value="VOTER">VOTER</option>
                            <option value="ADMIN">ADMIN</option>
                            <option value="SCRUTATEUR">SCRUTATEUR</option>
                            <option value="SUPERADMIN">SUPERADMIN</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Niveau</label>
                        <select class="form-select" id="ent-notif-level">
                            <option value="INFO">INFO</option>
                            <option value="SUCCESS">SUCCESS</option>
                            <option value="WARNING">WARNING</option>
                            <option value="ERROR">ERROR</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ent-notif-active" checked>
                            <label class="form-check-label" for="ent-notif-active">Active</label>
                        </div>
                    </div>
                    <div class="col-md-5 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ent-notif-is-push">
                            <label class="form-check-label" for="ent-notif-is-push">Push in-app</label>
                        </div>
                    </div>
                    <div class="col-md-5 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ent-notif-send-now">
                            <label class="form-check-label" for="ent-notif-send-now">Envoyer push maintenant</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Lien cible (optionnel)</label>
                        <input class="form-control" id="ent-notif-target" placeholder="/enterprise/elections.php">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" id="ent-notif-body" rows="3"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Debut</label>
                        <input type="datetime-local" class="form-control" id="ent-notif-starts">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fin</label>
                        <input type="datetime-local" class="form-control" id="ent-notif-ends">
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

