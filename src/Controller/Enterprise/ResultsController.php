<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise;

final class ResultsController extends BasePortalController
{
    public function __invoke(): string
    {
        user_require_login(false);
        user_require_role(['VOTER', 'ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], false);

        $userId = $this->auth()->currentId();
        if ($userId === null) {
            header('Location: ' . app_url('/enterprise/login.php'));
            exit;
        }

        $stmt = $this->pdo->query('
            SELECT *
            FROM elections
            WHERE status IN (\'PUBLISHED\',\'CLOSED\',\'ARCHIVED\')
            ORDER BY start_at DESC
        ');
        $rows = $stmt->fetchAll() ?: [];

        $cards = [];
        foreach ($rows as $election) {
            $electionId = (int)($election['id'] ?? 0);
            if ($electionId <= 0) {
                continue;
            }

            $eligible = $this->elections()->isUserEligible($this->pdo, $electionId, $userId);
            $voted = $this->elections()->userHasParticipated($this->pdo, $electionId, $userId);
            $status = (string)($election['status'] ?? 'DRAFT');
            $resultsAvailable = $eligible && in_array($status, ['CLOSED', 'ARCHIVED'], true);

            $cards[] = [
                'election' => $election,
                'eligible' => $eligible,
                'voted' => $voted,
                'results_available' => $resultsAvailable,
            ];
        }

        $selectedId = (int)($_GET['id'] ?? 0);
        if ($selectedId <= 0) {
            foreach ($cards as $card) {
                if (!empty($card['results_available'])) {
                    $selectedId = (int)($card['election']['id'] ?? 0);
                    break;
                }
            }
            if ($selectedId <= 0 && $cards) {
                $selectedId = (int)($cards[0]['election']['id'] ?? 0);
            }
        }

        return $this->view->render('enterprise/results', $this->portalContext() + [
            'pageTitle' => 'Resultats',
            'active' => 'results',
            'cards' => $cards,
            'selectedId' => $selectedId,
        ]);
    }
}
