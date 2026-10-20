<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\ISS\SpSoftwareExportResult97;
use DateTimeImmutable;

/** Bind municipal SP documentary conditions to the current two ISS contexts; not archived decisions or tax amounts. */
final readonly class CurrentPartialExportSp98
{
    public const CONTRACT = 'SP-CURRENT-PARTIAL-EXPORT-CONTEXT-2026.10.16-98';

    /**
     * @param  array<string, mixed>  $facts
     * @param  array<string, mixed>  $nationalPolicy
     * @param  array<string, mixed>  $municipalPolicy
     * @return array<string, mixed>
     */
    public function qualify(array $facts, LocalDate $on, DateTimeImmutable $knownAt, array $nationalPolicy, array $municipalPolicy): array
    {
        if (($municipalPolicy['current_binding_contract'] ?? null) !== self::CONTRACT
            || ($municipalPolicy['source_effective_date_proven'] ?? null) !== false) {
            return $this->stop('POLICY_NOT_BOUND');
        }
        unset($municipalPolicy['current_binding_contract'], $municipalPolicy['source_effective_date_proven']);
        $national = (new CurrentPartialExportIss96)->qualify($facts, $on, $knownAt, $nationalPolicy);
        if ($national['status'] !== 'LOCAL_CURRENT_EXPORT_ISS_RESULTS_BOUND') {
            return [...$this->stop('CURRENT_NATIONAL_RESULTS_NOT_BOUND'), 'national_reason' => $national['reason']];
        }
        if (($facts['provider.municipality_ibge'] ?? null) !== '3550308') {
            return $this->stop('SP_SUPPLIER_ESTABLISHMENT_REQUIRED', ['provider.municipality_ibge']);
        }
        $records = $facts['rtc.export_sp_result_proofs'] ?? null;
        if (! is_array($records) || ! array_is_list($records) || count($records) !== 2) {
            return $this->stop('TWO_MUNICIPAL_PROOFS_REQUIRED', ['rtc.export_sp_result_proofs']);
        }
        $byRole = [];
        foreach ($records as $record) {
            if (! is_array($record) || count($record) !== 2 || ! array_key_exists('role', $record)
                || ! array_key_exists('municipal_proof', $record)
                || ! in_array($record['role'], ['RTC_FOREIGN', 'RTC_DOMESTIC'], true)) {
                return $this->stop('EXACT_ROLE_PROOF_CONTRACT_REQUIRED');
            }
            if (array_key_exists($record['role'], $byRole)) {
                return $this->stop('DUPLICATE_MUNICIPAL_ROLE');
            }
            $byRole[$record['role']] = $record['municipal_proof'];
        }
        if (! array_key_exists('RTC_FOREIGN', $byRole) || ! array_key_exists('RTC_DOMESTIC', $byRole)) {
            return $this->stop('TWO_MUNICIPAL_PROOFS_REQUIRED');
        }
        $contexts = [];
        foreach ($facts['rtc.export_iss_result_contexts'] as $record) {
            $contexts[$record['role']] = $record['iss_context'];
        }
        $documents = [];
        foreach (['RTC_FOREIGN', 'RTC_DOMESTIC'] as $role) {
            $qualified = (new SpSoftwareExportResult97)->qualify($contexts[$role], $nationalPolicy, $byRole[$role], $municipalPolicy, $knownAt);
            if (($qualified['municipal_documentary_conditions_locally_bound'] ?? false) !== true) {
                return [...$this->stop('MUNICIPAL_RESULT_NOT_BOUND'), 'role' => $role, 'qualification_reason' => $qualified['reason']];
            }
            // The municipal component fixes3550308; bind that assertion to the actual provider fact as well.
            $documents[] = ['role' => $role, 'document_reference' => $contexts[$role]['document_id'], 'municipal_result' => $qualified];
        }

        return ['status' => 'LOCAL_CURRENT_SP_EXPORT_RESULTS_BOUND', 'reason' => 'RTC_EXPORT_SP98_MUNICIPAL_RESULTS_BOUND_NOT_DOCUMENTS',
            'missing_facts' => [], 'supplier_municipality' => $facts['provider.municipality_ibge'],
            'operation_reference' => $facts['rtc.export_operation_reference'], 'documents' => $documents,
            'source_mode' => 'CURRENT_FACTS_AFTER_ENGINE_NATIONAL_ISS_RESULT_STAGE', 'archive_snapshot_replayed' => false,
            'binding_hash' => CanonicalJson::hash(['operation' => $facts['rtc.export_operation_reference'], 'documents' => $documents,
                'national_binding_hash' => $national['binding_hash'], 'policy' => $municipalPolicy]),
            'current_facts_hash' => CanonicalJson::hash($facts), ...$this->limits()];
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['economic_operation_identity_authenticated' => false, 'fiscal_document_identifiers_qualified' => false,
            'iss_supply_date_materially_qualified' => false, 'documentary_currency_conversion_qualified' => false,
            'per_document_tax_amount_calculated' => false, 'full_operation_iss_rewritten' => false,
            'source_effective_date_proven' => false, 'legal_applicability_approved' => false,
            'xml_generated' => false, 'document_obligation_complete' => false, 'posting_authorized' => false, 'production_ready' => false];
    }

    /**
     * @param  list<string>  $missing
     * @return array<string, mixed>
     */
    private function stop(string $reason, array $missing = []): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_EXPORT_SP98_'.$reason, 'missing_facts' => $missing, ...$this->limits()];
    }
}
