<?php
declare(strict_types=1);

$self = basename($_SERVER['PHP_SELF'] ?? '');
$isAdmin = user_has_role('ADMIN') || user_has_role('SUPERADMIN');
$isSuperadmin = user_has_role('SUPERADMIN');
?>
<nav id="sidebar" class="sidebar js-sidebar">
    <div class="sidebar-content">
        <a class="sidebar-brand" href="<?=htmlspecialchars(app_url('/enterprise/admin/dashboard.php'))?>">
            <span class="align-middle"><i class="bi bi-shield-lock me-2"></i>Admin Enterprise</span>
        </a>
        <ul class="sidebar-nav">
            <li class="sidebar-header">Supervision</li>
            <li class="sidebar-item<?=$self==='dashboard.php'?' active':''?>">
                <a class="sidebar-link" href="<?=htmlspecialchars(app_url('/enterprise/admin/dashboard.php'))?>" <?=$self==='dashboard.php'?'aria-current="page"':''?>><i class="bi bi-speedometer2"></i><span class="align-middle">Dashboard</span></a>
            </li>
            <li class="sidebar-item<?=$self==='elections.php'?' active':''?>">
                <a class="sidebar-link" href="<?=htmlspecialchars(app_url('/enterprise/admin/elections.php'))?>" <?=$self==='elections.php'?'aria-current="page"':''?>><i class="bi bi-ui-checks-grid"></i><span class="align-middle">Élections</span></a>
            </li>
            <li class="sidebar-item<?=$self==='voter-roll.php'?' active':''?>">
                <a class="sidebar-link" href="<?=htmlspecialchars(app_url('/enterprise/admin/voter-roll.php'))?>" <?=$self==='voter-roll.php'?'aria-current="page"':''?>><i class="bi bi-list-check"></i><span class="align-middle">Émargement</span></a>
            </li>
            <li class="sidebar-item<?=$self==='results.php'?' active':''?>">
                <a class="sidebar-link" href="<?=htmlspecialchars(app_url('/enterprise/admin/results.php'))?>" <?=$self==='results.php'?'aria-current="page"':''?>><i class="bi bi-bar-chart-line"></i><span class="align-middle">Résultats</span></a>
            </li>
            <li class="sidebar-item<?=$self==='participation.php'?' active':''?>">
                <a class="sidebar-link" href="<?=htmlspecialchars(app_url('/enterprise/admin/participation.php'))?>" <?=$self==='participation.php'?'aria-current="page"':''?>><i class="bi bi-people"></i><span class="align-middle">Participation</span></a>
            </li>

            <?php if ($isAdmin || $isSuperadmin): ?>
            <li class="sidebar-header">Administration</li>
            <li class="sidebar-item<?=$self==='users.php'?' active':''?>">
                <a class="sidebar-link" href="<?=htmlspecialchars(app_url('/enterprise/admin/users.php'))?>" <?=$self==='users.php'?'aria-current="page"':''?>><i class="bi bi-person-gear"></i><span class="align-middle">Utilisateurs</span></a>
            </li>
            <li class="sidebar-item<?=$self==='groups.php'?' active':''?>">
                <a class="sidebar-link" href="<?=htmlspecialchars(app_url('/enterprise/admin/groups.php'))?>" <?=$self==='groups.php'?'aria-current="page"':''?>><i class="bi bi-diagram-3"></i><span class="align-middle">Groupes</span></a>
            </li>
            <li class="sidebar-item<?=$self==='candidates.php'?' active':''?>">
                <a class="sidebar-link" href="<?=htmlspecialchars(app_url('/enterprise/admin/candidates.php'))?>" <?=$self==='candidates.php'?'aria-current="page"':''?>><i class="bi bi-person-badge"></i><span class="align-middle">Candidats</span></a>
            </li>
            <li class="sidebar-item<?=$self==='notifications.php'?' active':''?>">
                <a class="sidebar-link" href="<?=htmlspecialchars(app_url('/enterprise/admin/notifications.php'))?>" <?=$self==='notifications.php'?'aria-current="page"':''?>><i class="bi bi-bell"></i><span class="align-middle">Notifications</span></a>
            </li>
            <?php endif; ?>

            <?php if ($isSuperadmin): ?>
            <li class="sidebar-header">Sécurité</li>
            <li class="sidebar-item<?=$self==='audit.php'?' active':''?>">
                <a class="sidebar-link" href="<?=htmlspecialchars(app_url('/enterprise/admin/audit.php'))?>" <?=$self==='audit.php'?'aria-current="page"':''?>><i class="bi bi-journal-check"></i><span class="align-middle">Audit</span></a>
            </li>
            <li class="sidebar-item<?=$self==='roles.php'?' active':''?>">
                <a class="sidebar-link" href="<?=htmlspecialchars(app_url('/enterprise/admin/roles.php'))?>" <?=$self==='roles.php'?'aria-current="page"':''?>><i class="bi bi-shield-lock"></i><span class="align-middle">Rôles</span></a>
            </li>
            <li class="sidebar-item<?=$self==='backups.php'?' active':''?>">
                <a class="sidebar-link" href="<?=htmlspecialchars(app_url('/enterprise/admin/backups.php'))?>" <?=$self==='backups.php'?'aria-current="page"':''?>><i class="bi bi-database-check"></i><span class="align-middle">Sauvegardes</span></a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
