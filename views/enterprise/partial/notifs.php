<?php
declare(strict_types=1);

/** @var bool $maintenance */
$maintenance = !empty($maintenance);
/** @var array $notifications */
$notifications = isset($notifications) && is_array($notifications) ? $notifications : [];

if ($maintenance): ?>
    <div class="alert alert-warning mb-3">
        <strong>Maintenance</strong>
        <div class="small mt-1">Les votes peuvent être temporairement bloqués.</div>
    </div>
<?php endif;

if ($notifications):
    foreach ($notifications as $n):
        $level = strtolower((string)($n['level'] ?? 'info'));
        $bs = $level === 'error' ? 'danger' : $level;
        ?>
        <div class="alert alert-<?=$bs?> mb-3">
            <strong><?=htmlspecialchars((string)($n['title'] ?? ''))?></strong>
            <?php if (!empty($n['body'])): ?>
                <div class="small mt-1"><?=nl2br(htmlspecialchars((string)$n['body']))?></div>
            <?php endif; ?>
        </div>
    <?php endforeach;
endif;

