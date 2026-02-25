<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/**
 * Cloture automatiquement les scrutins publies dont la date de fin est depassee.
 * Retourne le nombre de scrutins effectivement clotures.
 *
 * @param callable|null $onClose Callback optionnel appele apres chaque cloture.
 */
function election_auto_close_expired(PDO $pdo, ?callable $onClose = null): int
{
    try {
        // On charge uniquement les scrutins encore publies, donc potentiellement a cloturer.
        $rows = $pdo->query("SELECT id, title, end_at, timezone FROM elections WHERE status='PUBLISHED'")->fetchAll() ?: [];
    } catch (Throwable $e) {
        return 0;
    }

    if (!$rows) {
        return 0;
    }

    $closed = 0;
    $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    foreach ($rows as $row) {
        $electionId = (int)($row['id'] ?? 0);
        if ($electionId <= 0) continue;

        // Chaque scrutin compare sa fin dans son propre fuseau horaire.
        $tzName = (string)($row['timezone'] ?? 'UTC');
        try {
            $tz = new DateTimeZone($tzName);
        } catch (Throwable $tzErr) {
            $tz = new DateTimeZone('UTC');
        }

        try {
            $end = new DateTimeImmutable((string)$row['end_at'], $tz);
        } catch (Throwable $endErr) {
            // Si la date est invalide on ignore ce scrutin, sans casser le flux.
            continue;
        }

        $nowLocal = $nowUtc->setTimezone($tz);
        if ($nowLocal <= $end) continue;

        // Condition de course protegee: on cloture seulement si le statut est encore PUBLISHED.
        $st = $pdo->prepare("UPDATE elections SET status='CLOSED', closed_at=NOW(), updated_at=NOW() WHERE id=? AND status='PUBLISHED'");
        $st->execute([$electionId]);
        if ((int)$pdo->query('SELECT ROW_COUNT()')->fetchColumn() <= 0) {
            continue;
        }

        $closed++;
        $title = (string)($row['title'] ?? ('Scrutin #' . $electionId));

        if (function_exists('audit_event')) {
            try {
                // Trace d exploitation: cloture automatique.
                audit_event($pdo, 'ELECTION_AUTO_CLOSE', 'ELECTION', $electionId, ['title' => $title]);
            } catch (Throwable $auditErr) {
                // Non bloquant.
            }
        }

        try {
            // Notification de fin envoyee aux utilisateurs concernes.
            \Vote\Infrastructure\Composition\AppServices::userPush()
                ->pushElectionClosed($pdo, $electionId, $title);
        } catch (Throwable $pushErr) {
            // Non bloquant.
        }

        if ($onClose !== null) {
            try {
                $onClose($electionId, $title);
            } catch (Throwable $cbErr) {
                // Non bloquant.
            }
        }
    }

    return $closed;
}
