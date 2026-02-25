<?php
declare(strict_types=1);

/** @var bool $includeDataTables */
$includeDataTables = !empty($includeDataTables);
/** @var bool $includeChart */
$includeChart = !empty($includeChart);
?>
<div id="toast-zone" class="toast-container position-fixed top-0 end-0 p-3"></div>

<script>
window.APP_BASE_PATH = <?=json_encode(APP_BASE_PATH, JSON_UNESCAPED_SLASHES)?>;
window.CSRF_TOKEN = <?=json_encode(csrf_token(), JSON_UNESCAPED_SLASHES)?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc3/dist/js/adminlte.min.js"></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/adminlte-bridge.js'))?>"></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/ui-enterprise.js'))?>"></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/live-notifications.js'))?>"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<?php if ($includeDataTables): ?>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<?php endif; ?>
<?php if ($includeChart): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php endif; ?>
<script src="<?=htmlspecialchars(app_url('/assets/js/admin.js'))?>"></script>
