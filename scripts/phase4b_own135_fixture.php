<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SoftwareOwnRevenue135;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/** Capture synthetic proofs only; never represents an authenticated bank or taxpayer record. */
function own135Capture(array $proof): array
{
    unset($proof['origin'], $proof['source_hash']);
    $body = CanonicalJson::encode($proof);
    $proof['origin'] = ['reference' => 'SYNTHETIC-OWN-MEMO-'.$proof['evidence_id'], 'media_type' => 'application/json',
        'content' => $body, 'content_sha256' => hash('sha256', $body)];
    $proof['source_hash'] = CanonicalJson::hash($proof);

    return $proof;
}

function own135BankCapture(array $record): array
{
    $content = CanonicalJson::encode($record);

    return ['reference' => 'SYNTHETIC-BANK-'.$record['document_reference'], 'media_type' => 'application/json',
        'content' => $content, 'content_sha256' => hash('sha256', $content)];
}

/** Tests/native caller supplies a real fresh/replayed ISS result; facts and monetary records stay intact. */
function own135Proofs(array $freshIss): array
{
    $proofs = [];
    $calculator = new SoftwareOwnRevenue135;
    foreach ($freshIss['documents'] as $document) {
        $context = $calculator->context($freshIss, $document);
        $export = $document['role'] === 'RTC_FOREIGN'; // Explicit synthetic scenario choice, not a calculator rule.
        $country = $export ? 'US' : 'BR';
        $bank = $export ? own135BankCapture(['document_reference' => $context['document_reference'],
            'payer_id' => $context['customer_id'], 'payee_id' => $context['supplier_id'], 'payer_country' => $country,
            'settled_on' => '2026-09-30', 'currency' => 'BRL', 'credited_brl' => $context['composed_price_brl'],
            'foreign_exchange_inflow' => true]) : null;
        $proofs[] = own135Capture(['contract' => SoftwareOwnRevenue135::CONTRACT, 'evidence_id' => 'SYNTHETIC-OWN135-'.$document['role'],
            'observed_at' => SoftwareOwnRevenue135::AVAILABLE_AT, 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31',
            'revoked_at' => null, 'superseded_at' => null,
            'value' => ['source_policy_hash' => CanonicalJson::hash(SoftwareOwnRevenue135::policy()),
                'document' => $context, 'profile' => SoftwareOwnRevenue135::profile(),
                'revenue' => ['recognized_on' => '2026-09-25', 'competence' => '2026-09', 'recognition_basis' => 'ACCRUAL_REVENUE_LINE',
                    'service_revenue_brl' => $context['composed_price_brl'], 'treatment' => $export ? 'SERVICE_EXPORT_WITH_DIVISAS' : 'ORDINARY_CUMULATIVE',
                    'customer_country' => $country, 'bank_inflow' => $bank]]]);
    }

    return $proofs;
}
