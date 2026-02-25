<?php
declare(strict_types=1);
?>
<script>
window.APP_BASE_PATH = <?=json_encode(APP_BASE_PATH, JSON_UNESCAPED_SLASHES)?>;
window.CSRF_TOKEN = <?=json_encode(csrf_token(), JSON_UNESCAPED_SLASHES)?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-rc3/dist/js/adminlte.min.js"></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/adminlte-bridge.js'))?>"></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/ui-enterprise.js'))?>"></script>
<script src="<?=htmlspecialchars(app_url('/assets/js/live-notifications.js'))?>"></script>
