<?php

declare(strict_types=1);

/** @return array<string, mixed> */
function combined145Fixture(): array
{
    $root = dirname(__DIR__, 2).'/resources/fiscal/';
    $specs = [
        ['software', 'consumption-phase4b/regular-saas-2026.json', '2026.09.25-1'],
        ['education', 'consumption-integrated-2026.10.01-6/integrated-education-presential-2026.json', '2026.10.01-6'],
    ];
    $supplies = [];
    foreach ($specs as [$id, $file, $version]) {
        $scenario = json_decode((string) file_get_contents($root.'scenarios/'.$file), true, 512, JSON_THROW_ON_ERROR);
        $scenario['scenario_id'] = $id;
        $scenario['knowledge_at'] = '2026-11-03T12:00:00+00:00';
        $scenario['facts']['rtc.gross_currency'] = 'BRL';
        $supplies[] = ['id' => $id, 'description' => 'Independent '.$id.' supply', 'value_brl' => '1000.00',
            'allocation_evidence_id' => 'FIXTURE.CONTRACT.LINE.'.$id,
            'bundle' => 'bundles/consumption-phase4b-'.$version.'.json', 'scenario' => $scenario];
    }

    return ['operation_id' => 'FIXTURE.COMBINED.145', 'contract_id' => 'FIXTURE.CONTRACT.145',
        'supplier_id' => '11111111000191', 'acquirer_id' => '22222222000191',
        'knowledge_at' => '2026-11-03T12:00:00+00:00', 'relationship' => 'INDEPENDENT_SUPPLIES',
        'relationship_evidence_id' => 'FIXTURE.INDEPENDENCE', 'allocation_evidence_id' => 'FIXTURE.SPECIFIED.PRICES',
        'currency' => 'BRL', 'declared_gross_brl' => '2000.00', 'supplies' => $supplies];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $directory = dirname(__DIR__, 2).'/storage/app/agent-a-A145';
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    $input = combined145Fixture();
    file_put_contents($directory.'/operation.json', json_encode($input, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $input['declared_gross_brl'] = '1999.99';
    file_put_contents($directory.'/invalid-sum.json', json_encode($input, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo $directory.PHP_EOL;
}
