<?php
declare(strict_types=1);

/** @var string $pageTitle */
$pageTitle = isset($pageTitle) ? (string)$pageTitle : 'Sauvegardes';
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
                    <h1 class="h3 mb-3">Sauvegardes</h1>
                    <div class="card">
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Recommandé :</strong> utiliser <code>mysqldump</code> (ou export phpMyAdmin).
                                Un script est fourni pour lancer un dump depuis la machine serveur.
                            </div>

                            <h2 class="h5">Backup (CLI)</h2>
                            <p class="mb-2">Depuis <code>c:\wamp64\www\vote</code> :</p>
                            <pre class="bg-light p-3 rounded"><code>php scripts/backup.php</code></pre>

                            <h2 class="h5 mt-4">Restauration</h2>
                            <p class="mb-2">Importe le fichier SQL via phpMyAdmin (ou <code>mysql</code> en CLI).</p>

                            <h2 class="h5 mt-4">Mode maintenance</h2>
                            <p class="mb-2">Tu peux bloquer le vote via <code>MAINTENANCE=1</code> dans <code>.env</code> (portail votant).</p>
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


