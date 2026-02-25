<?php
declare(strict_types=1);

namespace Vote\Controller\Enterprise;

final class ElectionsController extends BasePortalController
{
    public function __invoke(): string
    {
        user_require_login(false);
        user_require_role(['VOTER', 'ADMIN', 'SCRUTATEUR', 'SUPERADMIN'], false);

        $pageTitle = 'Scrutins';
        $userId = $this->auth()->currentId();
        if ($userId === null) {
            header('Location: ' . app_url('/enterprise/login.php'));
            exit;
        }

        $stmt = $this->pdo->query("SELECT * FROM elections WHERE status IN ('PUBLISHED','CLOSED','ARCHIVED') ORDER BY start_at DESC");
        $elections = $stmt->fetchAll() ?: [];

        $cards = ['ongoing' => [], 'upcoming' => [], 'past' => []];
        foreach ($elections as $el) {
            $electionId = (int)($el['id'] ?? 0);
            $open = $this->elections()->isOpen($el);
            $eligible = $electionId > 0 ? $this->elections()->isUserEligible($this->pdo, $electionId, $userId) : false;
            $voted = $electionId > 0 ? $this->elections()->userHasParticipated($this->pdo, $electionId, $userId) : false;
            $phase = $this->elections()->phase($el);

            $card = [
                'election' => $el,
                'phase' => $phase,
                'open' => $open,
                'eligible' => $eligible,
                'voted' => $voted,
                'can_results' => $eligible && in_array((string)($el['status'] ?? ''), ['CLOSED', 'ARCHIVED'], true),
            ];
            $cards[$phase][] = $card;
        }

        return $this->view->render('enterprise/elections', $this->portalContext() + [
            'pageTitle' => $pageTitle,
            'active' => 'elections',
            'cards' => $cards,
        ]);
    }
}
