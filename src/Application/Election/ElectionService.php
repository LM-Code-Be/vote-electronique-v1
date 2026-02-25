<?php
declare(strict_types=1);

namespace Vote\Application\Election;

use PDO;
use Throwable;
use Vote\Application\Audit\AuditTrail;
use Vote\Application\Logging\ActionLogger;
use Vote\Application\Notification\UserPushService;
use Vote\Domain\Election\ElectionRules;

final class ElectionService
{
    public function __construct(
        private readonly ElectionRules $rules,
        private readonly ActionLogger $logger,
        private readonly AuditTrail $auditTrail,
        private readonly \Closure $maintenanceChecker,
        private readonly ?UserPushService $userPush = null
    ) {
    }

    public function get(PDO $pdo, int $electionId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM elections WHERE id=? LIMIT 1');
        $stmt->execute([$electionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function isOpen(array $election): bool
    {
        return $this->rules->isOpen($election);
    }

    public function phase(array $election): string
    {
        return $this->rules->phase($election);
    }

    public function isUserEligible(PDO $pdo, int $electionId, int $userId): bool
    {
        $stmt = $pdo->prepare('SELECT status, user_type FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        $status = (string)($user['status'] ?? '');
        if ($status !== 'ACTIVE') {
            return false;
        }

        $userType = strtoupper((string)($user['user_type'] ?? 'INTERNAL'));
        if (!in_array($userType, ['INTERNAL', 'EXTERNAL'], true)) {
            $userType = 'INTERNAL';
        }

        $stmt = $pdo->prepare('SELECT audience_mode FROM elections WHERE id=? LIMIT 1');
        $stmt->execute([$electionId]);
        $audienceMode = strtoupper((string)($stmt->fetchColumn() ?: 'INTERNAL'));
        if (!in_array($audienceMode, ['INTERNAL', 'HYBRID', 'EXTERNAL'], true)) {
            $audienceMode = 'INTERNAL';
        }
        if ($audienceMode === 'INTERNAL' && $userType !== 'INTERNAL') {
            return false;
        }
        if ($audienceMode === 'EXTERNAL' && $userType !== 'EXTERNAL') {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id=? AND r.code='VOTER'
        ");
        $stmt->execute([$userId]);
        if ((int)$stmt->fetchColumn() <= 0) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT eligible FROM voter_roll WHERE election_id=? AND user_id=? LIMIT 1');
        $stmt->execute([$electionId, $userId]);
        $row = $stmt->fetch();
        if ($row) {
            return !empty($row['eligible']);
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM election_groups WHERE election_id=?');
        $stmt->execute([$electionId]);
        $hasGroups = (int)$stmt->fetchColumn() > 0;
        if (!$hasGroups) {
            return true;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM election_groups eg
            JOIN user_groups ug ON ug.group_id = eg.group_id
            WHERE eg.election_id=? AND ug.user_id=?
        ");
        $stmt->execute([$electionId, $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function candidates(PDO $pdo, int $electionId, bool $onlyValidated = true): array
    {
        $where = 'WHERE (election_id IS NULL OR election_id=?)';
        $params = [$electionId];
        if ($onlyValidated) {
            $where .= ' AND is_validated=1 AND is_active=1';
        }

        $order = 'ORDER BY display_order ASC, full_name ASC';
        $stmt = $pdo->prepare("SELECT * FROM candidates {$where} {$order}");
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function userHasParticipated(PDO $pdo, int $electionId, int $userId): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM participations WHERE election_id=? AND user_id=? LIMIT 1');
        $stmt->execute([$electionId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function castBallot(PDO $pdo, array $election, int $userId, array $payload): array
    {
        if (($this->maintenanceChecker)()) {
            throw new \RuntimeException('Maintenance en cours');
        }

        $electionId = (int)$election['id'];
        $type = (string)$election['type'];
        $isAnonymous = !empty($election['is_anonymous']);
        $allowChange = !empty($election['allow_vote_change']);

        if ($isAnonymous && $allowChange) {
            $allowChange = false;
        }

        if (!$this->isOpen($election)) {
            throw new \RuntimeException('Vote ferme');
        }
        if (!$this->isUserEligible($pdo, $electionId, $userId)) {
            throw new \RuntimeException('Non eligible');
        }

        $already = $this->userHasParticipated($pdo, $electionId, $userId);
        if ($already && !$allowChange) {
            throw new \RuntimeException('Deja vote');
        }

        $choices = $payload['choices'] ?? null;
        $yesno = $payload['yesno'] ?? null;
        $ranking = $payload['ranking'] ?? null;

        $items = [];

        if ($type === 'YESNO') {
            $value = strtoupper((string)$yesno);
            if (!in_array($value, ['YES', 'NO'], true)) {
                throw new \RuntimeException('Choix invalide');
            }
            $items[] = ['candidate_id' => null, 'value_yesno' => $value, 'rank_pos' => null];
        } elseif ($type === 'RANKED') {
            if (!is_array($ranking) || count($ranking) < 1) {
                throw new \RuntimeException('Classement requis');
            }
            $rank = 1;
            foreach ($ranking as $candidateId) {
                $candidateId = (int)$candidateId;
                if ($candidateId <= 0) {
                    continue;
                }
                $items[] = ['candidate_id' => $candidateId, 'value_yesno' => null, 'rank_pos' => $rank++];
            }
            if (!$items) {
                throw new \RuntimeException('Classement invalide');
            }
        } elseif ($type === 'MULTI') {
            if (!is_array($choices) || count($choices) < 1) {
                throw new \RuntimeException('Selection requise');
            }
            $max = (int)($election['max_choices'] ?? 0);
            $selected = array_values(array_unique(array_map('intval', $choices)));
            $selected = array_values(array_filter($selected, static fn (int $x): bool => $x > 0));
            if ($max > 0 && count($selected) > $max) {
                throw new \RuntimeException('Trop de choix');
            }
            foreach ($selected as $candidateId) {
                $items[] = ['candidate_id' => $candidateId, 'value_yesno' => null, 'rank_pos' => null];
            }
        } else {
            $candidateId = (int)($payload['choice'] ?? 0);
            if ($candidateId <= 0) {
                throw new \RuntimeException('Selection requise');
            }
            $items[] = ['candidate_id' => $candidateId, 'value_yesno' => null, 'rank_pos' => null];
        }

        if (in_array($type, ['SINGLE', 'MULTI', 'RANKED'], true)) {
            $validIds = [];
            foreach ($this->candidates($pdo, $electionId, true) as $candidate) {
                $validIds[(int)$candidate['id']] = true;
            }
            foreach ($items as $item) {
                if ($item['candidate_id'] !== null && empty($validIds[(int)$item['candidate_id']])) {
                    throw new \RuntimeException('Candidat invalide');
                }
            }
        }

        $pdo->beginTransaction();
        try {
            if ($already && $allowChange) {
                $pdo->prepare('DELETE FROM ballot_items WHERE ballot_id IN (SELECT id FROM ballots WHERE election_id=? AND user_id=?)')
                    ->execute([$electionId, $userId]);
                $pdo->prepare('DELETE FROM ballots WHERE election_id=? AND user_id=?')->execute([$electionId, $userId]);
                $pdo->prepare('DELETE FROM participations WHERE election_id=? AND user_id=?')->execute([$electionId, $userId]);
            }

            $pdo->prepare('INSERT INTO participations(election_id,user_id) VALUES(?,?)')->execute([$electionId, $userId]);

            $receipt = bin2hex(random_bytes(32));
            $receiptHash = hash('sha256', $receipt);

            $ballotUserId = $isAnonymous ? null : $userId;
            $stmt = $pdo->prepare('INSERT INTO ballots(election_id,user_id,receipt_hash) VALUES(?,?,?)');
            $stmt->execute([$electionId, $ballotUserId, $receiptHash]);
            $ballotId = (int)$pdo->lastInsertId();

            $insertItem = $pdo->prepare('INSERT INTO ballot_items(ballot_id,candidate_id,value_yesno,rank_pos) VALUES(?,?,?,?)');
            foreach ($items as $item) {
                $insertItem->execute([$ballotId, $item['candidate_id'], $item['value_yesno'], $item['rank_pos']]);
            }

            $pdo->commit();
            $this->logger->log($pdo, 'vote_cast', null, 'election_id=' . $electionId . ' anonymous=' . ($isAnonymous ? '1' : '0'), $userId);
            $this->auditTrail->record($pdo, 'VOTE_CAST', 'ELECTION', $electionId, ['anonymous' => $isAnonymous ? 1 : 0], $userId);
            if ($this->userPush !== null) {
                try {
                    $title = (string)($election['title'] ?? ('Scrutin #' . $electionId));
                    $this->userPush->pushVoteCastToUser($pdo, $userId, $electionId, $title);
                } catch (Throwable $e) {
                    // Non bloquant: le vote reste valide.
                }
            }

            return [
                'receipt' => $receipt,
                'receipt_hash' => $receiptHash,
                'anonymous' => $isAnonymous,
                'allow_change' => $allowChange,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
