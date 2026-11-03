<?php

declare(strict_types=1);

require_once __DIR__.'/combined145_fixture.php';

/** @return array<string, mixed> */
function combined146Fixture(): array
{
    $input = combined145Fixture();
    $principal = $input['supplies'][0];
    unset($input['supplies'], $input['allocation_evidence_id'], $principal['value_brl'], $principal['allocation_evidence_id']);
    $principal['specification_evidence_id'] = 'FIXTURE.MAIN.SOFTWARE.CONTRACT';
    $input['operation_id'] = 'FIXTURE.PRINCIPAL.146';
    $input['relationship'] = 'PRINCIPAL_ACCESSORIES';
    $input['declared_gross_brl'] = '1000.00';
    $input['principal'] = $principal;
    $input['accessories'] = [
        ['id' => 'activation', 'description' => 'Activation required to access the supplied software',
            'principal_id' => 'software', 'role' => 'CONDITION', 'independent_supply' => false,
            'relationship_evidence_id' => 'FIXTURE.REQUIRED.ACTIVATION'],
        ['id' => 'delivery', 'description' => 'Delivery means for the same software supply',
            'principal_id' => 'software', 'role' => 'MEANS', 'independent_supply' => false,
            'relationship_evidence_id' => 'FIXTURE.SOFTWARE.DELIVERY'],
    ];

    return $input;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $directory = dirname(__DIR__, 2).'/storage/app/agent-a-A146';
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    $input = combined146Fixture();
    file_put_contents($directory.'/operation.json', json_encode($input, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $input['accessories'][0]['independent_supply'] = true;
    file_put_contents($directory.'/independence-conflict.json', json_encode($input, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo $directory.PHP_EOL;
}
