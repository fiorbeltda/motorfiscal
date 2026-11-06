<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/scripts/agent_a/domicile151_fixture.php';

/** Natural person of art. 105 (Decreto 12.955/2026) acting through registered establishments. @return array<string, mixed> */
function b26Fixture(array $registryChanges = [], string $on = '2026-10-20', string $known = '2026-11-03T12:30:00Z'): array
{
    $input = domicile151Fixture();
    $input['operation_date'] = $on;
    $input['knowledge_at'] = $known;
    $record = array_replace($input['registry_evidence']['record'], ['party_kind' => 'NATURAL_PERSON',
        'natural_person_subject_to_registration105' => true, 'natural_person105_role' => 'TAXPAYER',
        'natural_person105_activity_evidence_id' => 'B26.ACTIVITY.EVIDENCE', 'registration_scheme' => 'CPF'], $registryChanges);
    foreach ($registryChanges as $key => $value) {
        if ($value === null) {
            unset($record[$key]);
        }
    }
    $input['registry_evidence'] = domicile151Envelope($record);
    foreach (['scope_evidence', 'registry_evidence', 'supply_establishment_evidence'] as $key) {
        $input[$key] = domicile151Envelope(['observed_at' => $known] + $input[$key]['record']);
    }

    return domicile151Rebind($input);
}

it('uses the supplied establishment of an art. 105 natural person as domicile (art. 107, I)', function (): void {
    $result = domicile151Qualifier()->qualify(b26Fixture());
    expect($result['status'])->toBe('LOCAL_ENTITY_DOMICILE_QUALIFIED')
        ->and($result['selected_establishment_id'])->toBe('BRANCH151')
        ->and($result['locality'])->toBe(['uf' => 'RJ', 'municipality_ibge' => '3304557'])
        ->and($result['source_article'])->toContain('Decreto 12.955/2026 arts. 105-107, I')
        ->and($result['natural_person105'])->toMatchArray(['role' => 'TAXPAYER', 'registration_scheme' => 'CPF',
            'cnpj_mandatory_on_operation_date' => false])
        ->and($result['natural_person105']['cbs_regulation_source']['sha256'])
        ->toBe(hash_file('sha256', resource_path('fiscal/sources/official/import130-decreto12955-observed20261022.html')))
        ->and(domicile151Qualifier()->replay($result))->toBeTrue();
});

it('accepts the responsible-party condition as well', function (): void {
    $result = domicile151Qualifier()->qualify(b26Fixture(['natural_person105_role' => 'RESPONSIBLE']));
    expect($result['status'])->toBe('LOCAL_ENTITY_DOMICILE_QUALIFIED')->and($result['natural_person105']['role'])->toBe('RESPONSIBLE');
});

it('requires CNPJ registration of the natural person from 2027-01-01 (art. 105 par. 4-A)', function (): void {
    $known = '2027-01-10T12:30:00Z';
    $cpf = domicile151Qualifier()->qualify(b26Fixture([], '2027-01-05', $known));
    expect($cpf['status'])->toBe('ACTION_REQUIRED')->and($cpf['reason'])->toBe('DOMICILE151_NATURAL_PERSON105_CNPJ_REGISTRATION_REQUIRED');
    $cnpj = domicile151Qualifier()->qualify(b26Fixture(['registration_scheme' => 'CNPJ'], '2027-01-05', $known));
    expect($cnpj['status'])->toBe('LOCAL_ENTITY_DOMICILE_QUALIFIED')
        ->and($cnpj['natural_person105']['cnpj_mandatory_on_operation_date'])->toBeTrue();
    $lastDay = domicile151Qualifier()->qualify(b26Fixture([], '2026-12-31', $known));
    expect($lastDay['status'])->toBe('LOCAL_ENTITY_DOMICILE_QUALIFIED');
});

it('does not apply the establishment rule without the art. 105 condition, role or activity evidence', function (array $changes, string $reason): void {
    expect(domicile151Qualifier()->qualify(b26Fixture($changes))['reason'])->toBe($reason);
})->with([
    'no art. 105 flag' => [['natural_person_subject_to_registration105' => null], 'DOMICILE151_NATURAL_PERSON_SEPARATE_QUALIFICATION_REQUIRED'],
    'flag false' => [['natural_person_subject_to_registration105' => false], 'DOMICILE151_NATURAL_PERSON_SEPARATE_QUALIFICATION_REQUIRED'],
    'role missing' => [['natural_person105_role' => null], 'DOMICILE151_NATURAL_PERSON105_CONDITION_UNPROVEN'],
    'role invalid' => [['natural_person105_role' => 'CONSUMER'], 'DOMICILE151_NATURAL_PERSON105_CONDITION_UNPROVEN'],
    'activity evidence blank' => [['natural_person105_activity_evidence_id' => ' '], 'DOMICILE151_NATURAL_PERSON105_CONDITION_UNPROVEN'],
]);

it('keeps the legal-entity establishment result byte-identical (no natural_person105 block)', function (): void {
    $result = domicile151Qualifier()->qualify(domicile151Fixture());
    expect($result['status'])->toBe('LOCAL_ENTITY_DOMICILE_QUALIFIED')->and($result)->not->toHaveKey('natural_person105')
        ->and($result['source_article'])->toBe('LC214 art.11 X; §3 I b');
});

it('still refuses an expired establishment of the natural person instead of falling back to another', function (): void {
    $input = b26Fixture();
    $record = $input['registry_evidence']['record'];
    $record['establishments'][1]['valid_until'] = '2026-10-19';
    $input['registry_evidence'] = domicile151Envelope($record);
    $result = domicile151Qualifier()->qualify(domicile151Rebind($input));
    expect($result['reason'])->toBe('DOMICILE151_SUPPLIED_ESTABLISHMENT_OUTSIDE_RECORDED_VALIDITY');
});
