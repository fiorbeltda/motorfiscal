<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

/** Reuses full XML/XSD composition with an explicit, freshly bound139 payment contract. */
final readonly class PartialExportFederalPair139
{
    /**
     * @param  array<array-key, mixed>  $snapshot
     * @param  array<array-key, mixed>  $declarations
     * @param  array<array-key, mixed>  $payments
     * @param  array<array-key, mixed>  $ownSelections
     * @return array<string, mixed>
     */
    public function project(array $snapshot, array $declarations, array $payments, array $ownSelections, string $root): array
    {
        $result = (new PartialExportFederalPair137)->project($snapshot, $declarations, $payments, $ownSelections, $root, new DocumentFederalPayment139);
        if (isset($result['reason'])) {
            $result['reason'] = str_replace('PAIR137_', 'PAIR139_', $result['reason']);
        }
        if ($result['status'] === 'LOCAL_OWN_AND_WITHHELD_PAIR_XML_XSD_VALIDATED') {
            $result['document_payment_contract'] = DocumentFederalPayment139::CONTRACT;
            $result['federal_knowledge_selection'] = DocumentFederalPayment139::policy()['federal_knowledge'];
        }

        return $result;
    }
}
