<?php

declare(strict_types=1);

use App\Modules\Fiscal\NFSe\ImportDocumentValues132;

require_once __DIR__.'/phase4b_import130_fixture.php';

/** @return array{array<string, mixed>, array<string, mixed>, array<string, mixed>, string} */
function phase4bImport132Fixture(): array
{
    [$snapshot, $identity] = phase4bImport130Fixture();
    $facts = $snapshot['input']['facts'];
    $bytes = "SYNTHETIC INVOICE ONLY\nforeign-invoice-1;line-1;BRL;1000.00\n";
    $document = ['contract' => ImportDocumentValues132::CONTRACT,
        'coverage' => 'SINGLE_SERVICE_LINE_ENTIRE_INVOICE', 'invoice_id' => $facts['rtc.import_document_id'],
        'line_id' => 'line-1', 'supplier_nif' => $facts['rtc.supplier_foreign_nif'],
        'acquirer_id' => $facts['rtc.acquirer_id'], 'service_id' => $facts['service.canonical_id'],
        'competence_date' => $snapshot['input']['competence_date'], 'currency' => 'BRL',
        'service_price' => '1000.00', 'source_evidence' => ['id' => 'SYNTHETIC-IMPORT132-INVOICE',
            'sha256' => hash('sha256', $bytes), 'observed_at' => '2026-10-22T17:34:00+00:00']];

    return [$snapshot, $identity, $document, $bytes];
}
