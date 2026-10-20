<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Invariants\RequiredDecisionPathInvariant;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\NFSe\DpsXmlProjector;

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function integratedInstitutionalImmunityResult(string $name = 'nonprofit-education', array $overrides = []): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-6.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-integrated-2026.10.02-6/integrated-institutional-immunity-'.$name.'-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $input['facts'] = array_replace($input['facts'], $overrides);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);

    return $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
}

it('integrates independent ISS and RTC immunities with the version101 issuer fields', function (string $name, string $type): void {
    $result = integratedInstitutionalImmunityResult($name);
    $decision = $result['decision'];
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')
        ->and($decision['iss']['incidence']['value'])->toBe('IMMUNE')
        ->and($decision['iss']['calculation']['tax_amount'])->toBe('0.00')
        ->and($decision['iss']['rate']['rate'])->toBeNull()
        ->and($decision['iss']['municipal_parameters']['status'])->toBe('NOT_APPLICABLE')
        ->and($decision['dps_projection']['iss']['tribISSQN'])->toBe('2')
        ->and($decision['dps_projection']['iss']['tpImunidade'])->toBe($type)
        ->and($decision['consumption_reform']['decision']['cbs']['nominal_amount'])->toBe('0.00')
        ->and($decision['federal_withholdings']['status'])->toBe('NOT_REQUESTED');
    $identity = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/dps-local-2026.10.01-6/presential-identity.json'), true, 512, JSON_THROW_ON_ERROR);
    $xml = (new DpsXmlProjector)->project($decision, $identity);
    expect($xml['status'])->toBe('PROJECTED')->and($xml['xml'])->toContain('<tribISSQN>2</tribISSQN>', '<tpImunidade>'.$type.'</tpImunidade>')
        ->and($xml['xml'])->not->toContain('<pAliq>');
})->with([['nonprofit-education', '3'], ['religious-entity', '2']]);

it('does not infer ISS immunity from RTC and demands independent essential purpose proof', function (string $name, string $reason, string $status): void {
    $result = integratedInstitutionalImmunityResult($name);
    expect($result['status'])->toBe($status)->and($result['reason_codes'])->toBe([$reason]);
})->with([
    ['missing-iss-proof', 'ISS_INSTITUTIONAL_IMMUNITY_PROOF_MISSING', 'ACTION_REQUIRED'],
    ['wrong-iss-owner', 'ISS_INSTITUTIONAL_IMMUNITY_SUPPLIER_OR_DOCUMENT_CONFLICT', 'INVALID'],
    ['wrong-iss-document', 'ISS_INSTITUTIONAL_IMMUNITY_SUPPLIER_OR_DOCUMENT_CONFLICT', 'INVALID'],
    ['expired-iss-proof', 'ISS_INSTITUTIONAL_IMMUNITY_PROOF_OUTSIDE_PERIOD', 'ACTION_REQUIRED'],
    ['unrelated-iss-service', 'ISS_INSTITUTIONAL_ESSENTIAL_PURPOSE_NOT_MET', 'INVALID'],
    ['missing-essential-evidence', 'ISS_INSTITUTIONAL_IMMUNITY_EVIDENCE_MISSING:essential_scope_evidence_id', 'ACTION_REQUIRED'],
    ['unknown-essential-scope', 'ISS_INSTITUTIONAL_ESSENTIAL_PURPOSE_UNPROVEN', 'ACTION_REQUIRED'],
    ['conflicting-rtc-treatment', 'ISS_RTC_INSTITUTIONAL_TREATMENT_CONFLICT', 'INVALID'],
]);

it('rejects ISS withholding performed on the qualified immune supply', function (): void {
    $result = integratedInstitutionalImmunityResult('nonprofit-education', ['operation.withholding_performed' => true]);
    expect($result['reason_codes'])->toBe(['ISS_IMMUNITY_WITHHOLDING_CONFLICT']);
});

it('evaluates the actual family payment through 4A without inferring a federal exemption from ISS', function (): void {
    $result = integratedInstitutionalImmunityResult('family-payer-with-4a');
    $federal = $result['decision']['federal_withholdings'];
    expect($result['status'])->toBe('VALID_WITH_WARNINGS')->and($federal['status'])->toBe('RESOLVED')
        ->and($federal['irrf']['status'])->toBe('NOT_APPLICABLE')->and($federal['inss']['status'])->toBe('NOT_APPLICABLE')
        ->and($federal['pcc']['status'])->toBe('NOT_APPLICABLE')->and($federal['total_withholding'])->toBe('0.00')
        ->and($federal['obligations']['status'])->toBe('NO_WITHHOLDING_EVENT');
    $identity = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/dps-local-2026.10.02-6/family-identity.json'), true, 512, JSON_THROW_ON_ERROR);
    $projected = (new DpsXmlProjector)->project($result['decision'], $identity);
    expect($projected['status'])->toBe('PROJECTED')->and($projected['xml'])->toContain('<CPF>12345678902</CPF>', '<tpRetPisCofins>0</tpRetPisCofins>');
});

it('does not exempt the taxable ISS path from the rate invariant', function (): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-6.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-integrated-2026.10.02-6/integrated-institutional-immunity-nonprofit-education-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $context = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input)->input->context;
    $invariant = new RequiredDecisionPathInvariant('ISS_RATE_REQUIRED', 'iss.rate.rate', exemptWhenPath: 'iss.incidence.value', exemptWhenValue: 'IMMUNE');
    expect($invariant->validate(['iss' => ['incidence' => ['value' => 'IMMUNE'], 'rate' => ['rate' => null]]], $context)->valid)->toBeTrue()
        ->and($invariant->validate(['iss' => ['incidence' => ['value' => 'TAXABLE'], 'rate' => ['rate' => null]]], $context)->valid)->toBeFalse()
        ->and($invariant->validate(['iss' => ['rate' => ['rate' => null]]], $context)->valid)->toBeFalse();
});

it('rejects a municipal immunity projection that claims a positive ISS amount', function (): void {
    $decision = integratedInstitutionalImmunityResult()['decision'];
    $decision['iss']['calculation']['tax_amount'] = '1.00';
    $identity = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/scenarios/dps-local-2026.10.01-6/presential-identity.json'), true, 512, JSON_THROW_ON_ERROR);
    expect((new DpsXmlProjector)->project($decision, $identity)['reason'])->toBe('RTC_DPS_ISS_IMMUNITY_DECISION_CONFLICT');
});
