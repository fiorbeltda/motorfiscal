<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\ISS\IssDocumentBase133;
use DateTimeImmutable;
use InvalidArgumentException;

/** New typed proof carrier; all prior financial facts remain bound by fresh133 qualification. */
final readonly class DocumentIssIntegration134
{
    public const VERSION = '2026.10.22-134-draft1';

    public const AVAILABLE_AT = '2026-10-22T18:36:00+00:00';

    public const PROOFS = 'rtc.export_iss_tax_context_proof_records';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => 'PARTIAL-EXPORT-DOCUMENT-ISS-134-V1', 'available_at' => self::AVAILABLE_AT,
            'base_policy' => IssDocumentBase133::policy(), 'proof_carrier' => self::PROOFS,
            'scope' => 'FRESH_PER_DOCUMENT_ISS_NOT_DOCUMENT_OBLIGATION_FULFILLMENT',
            'document_obligation_complete' => false, 'production_ready' => false];
    }

    /** @param array<string, mixed> $codes */
    public static function required(array $codes): bool
    {
        return ($codes['version'] ?? null) === self::VERSION || array_key_exists('document_iss_policy134', $codes);
    }

    /** @param array<string, mixed> $codes
     * @return array<string, mixed>
     */
    public function qualifyPolicy(array $codes, DateTimeImmutable $known): array
    {
        $policy = $codes['document_iss_policy134'] ?? null;
        if (! is_array($policy) || CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy())
            || $known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_ISS134_SOURCE_POLICY_NOT_BOUND'];
        }

        return ['status' => 'RESOLVED', 'policy_hash' => CanonicalJson::hash($policy)];
    }

    public static function withoutProofCarrier(FactBag $source): FactBag
    {
        $copy = new FactBag(new FactDefinitionRegistry($source->definitions()));
        foreach ($source->all() as $fact) {
            if ($fact->key !== self::PROOFS) {
                $copy->put($fact);
            }
        }

        return $copy;
    }

    /** @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    public function qualify(FactBag $source, DateTimeImmutable $known, array $policy): array
    {
        if (CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy()) || $known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('EXACT_POLICY_REQUIRED');
        }
        $proofs = $source->value(self::PROOFS);
        if (! is_array($proofs) || ! array_is_list($proofs) || count($proofs) !== 2) {
            return [...$this->stop('TWO_TAX_CONTEXT_PROOFS_REQUIRED'), 'missing_facts' => [self::PROOFS]];
        }
        $facts = self::withoutProofCarrier($source);
        $required = ['rtc.export_iss_material_records', 'rtc.export_iss_dated_result_records',
            'rtc.export_dated_dps_identity_records', 'provider.iss_regime_proof_records',
            DocumentPriceFactProjection112::PRICE_RECORDS, DocumentPriceFactProjection112::COMPOSITION_RECORDS,
            DocumentPriceFactProjection112::PRICE_PROOFS, DocumentPriceFactProjection112::COMPOSITION_PROOFS];
        if ($facts->missing($required) !== []) {
            return [...$this->stop('PREDECESSOR_INPUTS_REQUIRED'), 'missing_facts' => $facts->missing($required)];
        }
        foreach ($required as $key) {
            if (! is_array($facts->value($key))) {
                return $this->stop('EXACT_PREDECESSOR_ARRAYS_REQUIRED');
            }
        }
        foreach ([DocumentPriceFactProjection112::PRICE_PROOFS, DocumentPriceFactProjection112::COMPOSITION_PROOFS] as $key) {
            $records = $facts->value($key);
            if (! array_is_list($records) || count($records) !== 1 || ! is_array($records[0])) {
                return $this->stop('EXACT_SINGLETON_PRICE_PROOFS_REQUIRED');
            }
        }
        try {
            $projection = DocumentPriceFactProjection112::from($facts);
        } catch (InvalidArgumentException) {
            return $this->stop('EXACT_PROJECTION_CONTEXT_REQUIRED');
        }
        $input110 = ['facts' => $projection['facts'], 'material_records' => $facts->value('rtc.export_iss_material_records'),
            'result_records' => $facts->value('rtc.export_iss_dated_result_records'),
            'identity_records' => $facts->value('rtc.export_dated_dps_identity_records'),
            'price_records' => $facts->value(DocumentPriceFactProjection112::PRICE_RECORDS),
            'proof' => $facts->value(DocumentPriceFactProjection112::PRICE_PROOFS)[0]];
        $input111 = ['input110' => $input110, 'records' => $facts->value(DocumentPriceFactProjection112::COMPOSITION_RECORDS),
            'proof' => $facts->value(DocumentPriceFactProjection112::COMPOSITION_PROOFS)[0]];
        $result = (new IssDocumentBase133)->calculate($input111, $proofs, $known->format('Y-m-d\TH:i:s.uP'), $policy['base_policy']);

        return [...$result, 'missing_facts' => [], 'source_current_facts_hash' => CanonicalJson::hash($source->canonicalValues()),
            'proof_carrier_excluded_only' => self::PROOFS, 'qualification_context_facts_hash' => CanonicalJson::hash($projection['facts'])];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_ISS134_'.$reason, 'missing_facts' => [],
            'iss_base_calculated' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }
}
