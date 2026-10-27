<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\ImportNt009Model142;

require_once __DIR__.'/phase4b_import141_fixture.php';

/** New synthetic invoice with an explicitly numeric original identity, never a rewritten141 invoice. */
function import142Scenario(array $knowledge, string $invoiceId = '0002026001'): array
{
    $input = import141Scenario($knowledge);
    $input['scenario_id'] = 'SYNTHETIC-NT009-IMPORT142';
    $input['knowledge_at'] = (new DateTimeImmutable(ImportNt009Model142::AVAILABLE_AT))->modify('+5 seconds')->format('Y-m-d\TH:i:s.uP');
    foreach (['rtc.document_id', 'rtc.import_document_id', 'iss.document_reference'] as $key) {
        $input['facts'][$key] = $invoiceId;
    }
    $proof = $input['facts']['rtc.import_fx_proof'][0];
    $proof['observed_at'] = $input['knowledge_at'];
    $proof['value']['document_id'] = $invoiceId;
    $proof['value']['foreign_document_id'] = $invoiceId;
    unset($proof['source_hash']);
    $proof['source_hash'] = CanonicalJson::hash($proof);
    $input['facts']['rtc.import_fx_proof'] = [$proof];
    $proof = $input['facts']['iss.document_currency_proof_records'][0];
    $proof['observed_at'] = $input['knowledge_at'];
    $proof['value']['document_reference'] = $invoiceId;
    $input['facts']['iss.document_currency_proof_records'] = [own135Capture($proof)];

    return $input;
}

function import142Comext(array $input): array
{
    return ['contract' => ImportNt009Model142::CONTRACT, 'invoice_id' => $input['facts']['rtc.document_id'],
        'evidence_id' => 'SYNTHETIC-EXPLICIT-COMEXT142', 'observed_at' => $input['knowledge_at'],
        'payment_evidence_id' => null,
        'comext' => ['mdPrestacao' => '1', 'vincPrest' => '0', 'tpMoeda' => '220', 'mecAFComexP' => '01',
            'mecAFComexT' => '01', 'movTempBens' => '1', 'mdic' => '0', 'nContCambio' => null]];
}
