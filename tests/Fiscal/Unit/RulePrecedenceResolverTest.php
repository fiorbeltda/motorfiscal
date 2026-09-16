<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Precedence\CandidateDecision;
use App\Modules\Fiscal\Core\Precedence\PrecedenceStatus;
use App\Modules\Fiscal\Core\Precedence\RulePrecedenceResolver;
use App\Modules\Fiscal\Core\Precedence\RuleRelation;
use App\Modules\Fiscal\Core\Precedence\RuleRelationType;

it('resolves only explicit legal relations', function (): void {
    $result = (new RulePrecedenceResolver)->resolve([
        new CandidateDecision('ISS.GENERAL', 'iss.location', 'PROVIDER'),
        new CandidateDecision('ISS.CONSTRUCTION', 'iss.location', 'WORK'),
    ], [
        new RuleRelation('ISS.CONSTRUCTION', 'ISS.GENERAL', RuleRelationType::EXCEPTION_OF, 'LC116.ART3'),
    ]);

    expect($result->status)->toBe(PrecedenceStatus::RESOLVED)
        ->and($result->decision?->value)->toBe('WORK');
});

it('requires review when no explicit relation resolves candidates', function (): void {
    $result = (new RulePrecedenceResolver)->resolve([
        new CandidateDecision('RULE.A', 'iss.rate', '2.00'),
        new CandidateDecision('RULE.B', 'iss.rate', '5.00'),
    ], []);

    expect($result->status)->toBe(PrecedenceStatus::CONFLICT)
        ->and($result->remainingCandidates)->toHaveCount(2);
});
