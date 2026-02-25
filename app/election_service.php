<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Vote\Infrastructure\Composition\AppServices;

function election_get(PDO $pdo, int $electionId): ?array
{
    return AppServices::election()->get($pdo, $electionId);
}

function election_is_open(array $election): bool
{
    return AppServices::election()->isOpen($election);
}

function election_is_user_eligible(PDO $pdo, int $electionId, int $userId): bool
{
    return AppServices::election()->isUserEligible($pdo, $electionId, $userId);
}

function election_candidates(PDO $pdo, int $electionId, bool $onlyValidated = true): array
{
    return AppServices::election()->candidates($pdo, $electionId, $onlyValidated);
}

function election_user_has_participated(PDO $pdo, int $electionId, int $userId): bool
{
    return AppServices::election()->userHasParticipated($pdo, $electionId, $userId);
}

function election_cast_ballot(PDO $pdo, array $election, int $userId, array $payload): array
{
    return AppServices::election()->castBallot($pdo, $election, $userId, $payload);
}
