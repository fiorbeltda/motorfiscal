<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @return array<string, mixed> */
function cooperativeZeroResult(string $name): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-9.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-integrated-2026.10.02-9/integrated-cooperative-zero-'.$name.'-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);

    return $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
}

it('applies article271 zero while keeping taxable base previous credit policy and ISS independent', function (string $name): void {
    $result = cooperativeZeroResult($name);
    $rtc = $result['decision']['consumption_reform'];
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')
        ->and($rtc['reason_codes'])->toBe(['RTC_COOPERATIVE_ZERO_RATE'])
        ->and($rtc['decision']['base']['taxable'])->toBe('1000.00')
        ->and($rtc['decision']['classification']['cclasstrib'])->toBe('200020')
        ->and($rtc['decision']['ibs']['status'])->toBe('ZERO_RATE')
        ->and($rtc['decision']['ibs']['nominal_amount'])->toBe('0.00')
        ->and($rtc['decision']['cbs']['nominal_amount'])->toBe('0.00')
        ->and($rtc['decision']['treatment']['incidence'])->toBe('TAXABLE')
        ->and($rtc['decision']['treatment']['supplier_previous_credit_treatment'])->toBe('PRESERVED_ART52_SUBJECT_TO_PROOF')
        ->and($result['decision']['iss']['calculation']['tax_amount'])->toBe('50.00')
        ->and($result['decision']['federal_withholdings']['status'])->toBe('NOT_REQUESTED');
})->with(['cooperative-to-member', 'member-to-cooperative', 'startup-option']);

it('evaluates the actual individual member payer through 4A', function (): void {
    $result = cooperativeZeroResult('individual-member-with-4a');
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')
        ->and($result['decision']['federal_withholdings']['status'])->toBe('RESOLVED')
        ->and($result['decision']['federal_withholdings']['total_withholding'])->toBe('0.00');
});

it('requires a proven option membership timing and route for cooperative zero', function (string $name, string $status, string $reason): void {
    $result = cooperativeZeroResult($name);
    expect($result['status'])->toBe($status)->and($result['reason_codes'])->toBe(['RTC_COOPERATIVE_ZERO_'.$reason]);
})->with([
    ['missing-proof', 'ACTION_REQUIRED', 'PROOF_MISSING'],
    ['wrong-document', 'INVALID', 'PARTIES_OR_DOCUMENT_CONFLICT'],
    ['wrong-parties', 'INVALID', 'PARTIES_OR_DOCUMENT_CONFLICT'],
    ['option-expired', 'ACTION_REQUIRED', 'PROOF_OUTSIDE_PERIOD'],
    ['option-not-effective', 'INVALID', 'CONDITION_NOT_MET:option_registered_and_effective'],
    ['unknown-membership', 'ACTION_REQUIRED', 'CONDITION_UNPROVEN:membership_or_participation_valid'],
    ['nonregular-member', 'INVALID', 'MEMBER_REGULAR_REGIME_REQUIRED'],
    ['wrong-route-parties', 'INVALID', 'ROUTE_PARTIES_CONFLICT'],
    ['current-year-option', 'INVALID', 'PRIOR_YEAR_OPTION_CONFLICT'],
    ['missing-regulatory-evidence', 'ACTION_REQUIRED', 'EVIDENCE_MISSING:option_regulatory_validation_evidence_id'],
    ['financial-service', 'ACTION_REQUIRED', 'OTHER_OPERATION_RULE_REQUIRED'],
    ['future-observation', 'INVALID', 'OPTION_PERIOD_CONFLICT'],
]);
