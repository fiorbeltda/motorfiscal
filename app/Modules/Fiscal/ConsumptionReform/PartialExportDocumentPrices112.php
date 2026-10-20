<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;
use InvalidArgumentException;

/** Typed transport adapter; reexecutes110/111 and their dated predecessors without cached assertions. */
final readonly class PartialExportDocumentPrices112
{
    public const CONTRACT = 'PARTIAL-EXPORT-DOCUMENT-PRICE-TRANSPORT-2026.10.19-112';

    public const AVAILABLE_AT = '2026-10-19T18:25:35.572132+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT,
            'composition_policy' => PartialExportIssPriceComposition111::policy(),
            'price_proof_transport' => 'EXACTLY_ONE_CAPTURED_CONTRACT_PRICE_PROOF',
            'composition_proof_transport' => 'EXACTLY_ONE_CAPTURED_COMPOSITION_PROOF',
            'monetary_records_remain_qualification_facts' => true,
            'legal_applicability_approved' => false, 'production_ready' => false];
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return array{status: string, reason: string, trace: list<array<string, mixed>>, missing_facts: list<string>}
     */
    public function qualify(FactBag $facts, DateTimeImmutable $knownAt, array $policy): array
    {
        if (CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy())) {
            return $this->stop('EXACT_TRANSPORT_POLICY_REQUIRED');
        }
        if ($knownAt < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('TRANSPORT_NOT_YET_AVAILABLE');
        }
        $missing = $facts->missing(['rtc.export_iss_material_records', 'rtc.export_iss_dated_result_records',
            'rtc.export_dated_dps_identity_records', 'provider.iss_regime', 'provider.iss_regime_proof_records',
            DocumentPriceFactProjection112::PRICE_RECORDS, DocumentPriceFactProjection112::PRICE_PROOFS,
            DocumentPriceFactProjection112::COMPOSITION_RECORDS, DocumentPriceFactProjection112::COMPOSITION_PROOFS]);
        if ($missing !== []) {
            return $this->stop('MATERIAL_RESULT_IDENTITY_AND_PRICE_RECORDS_REQUIRED', 'ACTION_REQUIRED', [], $missing);
        }
        foreach (['rtc.export_iss_material_records', 'rtc.export_iss_dated_result_records',
            'rtc.export_dated_dps_identity_records', DocumentPriceFactProjection112::PRICE_RECORDS,
            DocumentPriceFactProjection112::COMPOSITION_RECORDS] as $key) {
            if (! is_array($facts->value($key))) {
                return $this->stop('RECORD_ARRAYS_REQUIRED', 'INVALID');
            }
        }
        foreach (['provider.iss_regime_proof_records', DocumentPriceFactProjection112::PRICE_PROOFS,
            DocumentPriceFactProjection112::COMPOSITION_PROOFS] as $key) {
            $records = $facts->value($key);
            if (! is_array($records) || ! array_is_list($records) || count($records) !== 1 || ! is_array($records[0])) {
                return $this->stop('SINGLETON_PROOF_TRANSPORT_REQUIRED', 'ACTION_REQUIRED', [], [$key]);
            }
        }
        try {
            $projection = DocumentPriceFactProjection112::from($facts);
        } catch (InvalidArgumentException) {
            return $this->stop('EXACT_PROJECTION_CONTEXT_REQUIRED', 'INVALID');
        }
        $input = ['facts' => $projection['facts'], 'material_records' => $facts->value('rtc.export_iss_material_records'),
            'result_records' => $facts->value('rtc.export_iss_dated_result_records'),
            'identity_records' => $facts->value('rtc.export_dated_dps_identity_records'),
            'price_records' => $facts->value(DocumentPriceFactProjection112::PRICE_RECORDS),
            'proof' => $facts->value(DocumentPriceFactProjection112::PRICE_PROOFS)[0]];
        unset($projection['facts']);
        $trace = [['stage' => 'PARTIAL_EXPORT_DOCUMENT_PRICE_CONTEXT', 'status' => 'LOCAL_PRICE_CONTEXT_PROJECTED', ...$projection]];
        $known = $knownAt->format('Y-m-d\TH:i:s.uP');
        $compositionPolicy = $policy['composition_policy'];
        $price = (new PartialExportContractPrices110)->qualify($input['facts'], $input['material_records'],
            $input['result_records'], $input['identity_records'], $input['price_records'], $input['proof'],
            $known, $compositionPolicy['price_policy']);
        $trace[] = ['stage' => 'PARTIAL_EXPORT_CONTRACT_PRICES', ...$price];
        if (($price['contract_price_evidence_locally_bound'] ?? false) !== true) {
            return ['status' => $price['status'], 'reason' => $price['reason'], 'trace' => $trace, 'missing_facts' => []];
        }
        $composition = (new PartialExportIssPriceComposition111)->qualify($input,
            $facts->value(DocumentPriceFactProjection112::COMPOSITION_RECORDS),
            $facts->value(DocumentPriceFactProjection112::COMPOSITION_PROOFS)[0], $known, $compositionPolicy);
        $trace[] = ['stage' => 'PARTIAL_EXPORT_ISS_PRICE_COMPOSITION', ...$composition];

        return ['status' => $composition['status'], 'reason' => $composition['reason'],
            'trace' => $trace, 'missing_facts' => []];
    }

    /**
     * @param  list<array<string,mixed>>  $trace
     * @param  list<string>  $missing
     * @return array{status: string, reason: string, trace: list<array<string, mixed>>, missing_facts: list<string>}
     */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED', array $trace = [], array $missing = []): array
    {
        $reason = 'RTC_EXPORT_PRICE112_'.$reason;

        return ['status' => $status, 'reason' => $reason, 'missing_facts' => $missing,
            'trace' => [...$trace, ['stage' => 'PARTIAL_EXPORT_DOCUMENT_PRICE_CONTEXT', 'status' => $status, 'reason' => $reason]]];
    }
}
