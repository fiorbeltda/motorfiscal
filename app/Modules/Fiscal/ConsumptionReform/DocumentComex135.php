<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;

/** Bind explicit documentary COMEX claims to each fresh document, not to the RTC role label. */
final readonly class DocumentComex135
{
    public const CONTRACT = 'LOCAL-DOCUMENT-COMEX-135-V1';

    public const AVAILABLE_AT = '2026-10-22T19:02:10+00:00';

    /** @param array<string, mixed> $iss
     * @param  array<string, mixed>  $facts
     * @param  array<int|string, mixed>  $proofs
     * @param  array<string, mixed>  $codes
     * @return array<string, mixed>
     */
    public function qualify(array $iss, array $facts, array $proofs, array $codes, DateTimeImmutable $known): array
    {
        if ($known < new DateTimeImmutable(self::AVAILABLE_AT)
            || (new DpsLayoutPolicy127)->qualify($codes, $known)['status'] !== 'RESOLVED') {
            return $this->stop('CURRENT_OBSERVED_SCHEMA_POLICY_REQUIRED');
        }
        if (($iss['status'] ?? null) !== 'LOCAL_ISS_DOCUMENT_BASES_CALCULATED' || count($iss['documents'] ?? []) !== 2
            || ! array_is_list($proofs) || count($proofs) !== 2) {
            return $this->stop('TWO_FRESH_DOCUMENTS_AND_COMEX_PROOFS_REQUIRED');
        }
        $byRole = [];
        foreach ($proofs as $proof) {
            if (! is_array($proof) || ! is_array($proof['value'] ?? null)
                || ! is_string($proof['value']['role'] ?? null) || isset($byRole[$proof['value']['role']])) {
                return $this->stop('DISTINCT_COMEX_PROOFS_REQUIRED');
            }
            $byRole[$proof['value']['role']] = $proof;
        }
        $rows = [];
        foreach ($iss['documents'] as $doc) {
            $proof = $byRole[$doc['role']] ?? null;
            if (! is_array($proof) || count($proof) !== 10 || array_diff(array_keys($proof), ['contract', 'evidence_id',
                'observed_at', 'valid_from', 'valid_to', 'revoked_at', 'superseded_at', 'value', 'origin', 'source_hash']) !== []
                || $proof['contract'] !== self::CONTRACT || ! is_string($proof['evidence_id']) || trim($proof['evidence_id']) === '') {
                return $this->stop('EXACT_COMEX_PROOF_REQUIRED');
            }
            $observed = $this->instant($proof['observed_at']);
            if ($observed === null || $observed > $known || $observed < new DateTimeImmutable(self::AVAILABLE_AT)) {
                return $this->stop('COMEX_PROOF_NOT_KNOWN');
            }
            foreach ([DocumentPriceFactProjection112::PRICE_PROOFS, DocumentPriceFactProjection112::COMPOSITION_PROOFS, DocumentIssIntegration134::PROOFS] as $key) {
                $priorProofs = $facts[$key] ?? null;
                if (! is_array($priorProofs) || $priorProofs === []) {
                    return $this->stop('UPSTREAM_DOCUMENT_PROOF_OBSERVATIONS_REQUIRED');
                }
                foreach ($priorProofs as $prior) {
                    $priorObserved = $this->instant(is_array($prior) ? ($prior['observed_at'] ?? null) : null);
                    if ($priorObserved === null || $priorObserved > $observed) {
                        return $this->stop('COMEX_PROOF_PREDATES_DOCUMENT_PRICE_OR_ISS');
                    }
                }
            }
            foreach (['revoked_at', 'superseded_at'] as $key) {
                if ($proof[$key] !== null && ($this->instant($proof[$key]) === null || $this->instant($proof[$key]) <= $known)) {
                    return $this->stop('COMEX_PROOF_REVOKED_OR_SUPERSEDED');
                }
            }
            foreach (['valid_from', 'valid_to'] as $key) {
                $day = is_string($proof[$key]) ? DateTimeImmutable::createFromFormat('!Y-m-d', $proof[$key]) : false;
                if ($day === false || $day->format('Y-m-d') !== $proof[$key]) {
                    return $this->stop('EXACT_COMEX_COVERAGE_REQUIRED');
                }
            }
            if ($proof['valid_from'] > $doc['iss_supply_on'] || $proof['valid_to'] < $doc['iss_supply_on']) {
                return $this->stop('COMEX_PROOF_OUTSIDE_DOCUMENT_DATE');
            }
            if ($doc['price_currency'] !== 'BRL' || $doc['gross_service_price'] !== $doc['price_for_iss_evaluation_in_input_currency']
                || $doc['unconditional_discount'] !== '0.00' || $doc['conditional_discount'] !== '0.00') {
                return $this->stop('COMEX_PRICE_OR_CURRENCY_PROFILE_NOT_MAPPED');
            }
            $value = $proof['value'];
            $trade = $value['trade_fields'] ?? null;
            $performance = $value['performance_claim'] ?? null;
            $keys = ['mdPrestacao', 'vincPrest', 'tpMoeda', 'vServMoeda', 'mecAFComexP', 'mecAFComexT', 'movTempBens', 'mdic'];
            if (! is_array($trade) || count($trade) !== count($keys) || array_diff(array_keys($trade), $keys) !== []
                || ! is_array($performance) || count($performance) !== 4
                || array_diff(array_keys($performance), ['execution_country', 'consumer_presence_country', 'temporary_people_movement', 'evidence_reference']) !== []
                || ! is_string($performance['evidence_reference'] ?? null) || trim($performance['evidence_reference']) === '') {
                return $this->stop('EXACT_COMEX_FIELDS_AND_PERFORMANCE_CLAIM_REQUIRED');
            }
            if ($performance['execution_country'] !== 'BR' || $performance['temporary_people_movement'] !== false
                || ! is_string($performance['consumer_presence_country'])
                || preg_match('/\A[A-Z]{2}\z/', $performance['consumer_presence_country']) !== 1) {
                return $this->stop('COMEX_PERFORMANCE_PROFILE_NOT_MAPPED');
            }
            // Mode follows the explicitly evidenced supply performance, not the RTC_FOREIGN/DOMESTIC label.
            $mode = $performance['consumer_presence_country'] === 'BR' ? '2' : '1';
            if ($trade['mdPrestacao'] !== $mode || $trade['vincPrest'] !== '0' || $trade['tpMoeda'] !== '790'
                || $trade['vServMoeda'] !== $doc['gross_service_price'] || $trade['mecAFComexP'] !== '01'
                || $trade['mecAFComexT'] !== '01' || $trade['movTempBens'] !== '1' || $trade['mdic'] !== '0') {
                return $this->stop('COMEX_CLAIM_OR_DOCUMENT_VALUE_CONFLICT');
            }
            $expected = ['role' => $doc['role'], 'document_reference' => $doc['document_reference'],
                'contract_reference' => $doc['contract_reference'], 'unit_reference' => $doc['unit_reference'],
                'line_reference' => $doc['line_reference'], 'iss_supply_on' => $doc['iss_supply_on'],
                'supplier_id' => $facts['rtc.supplier_id'] ?? null, 'acquirer_id' => $facts['rtc.acquirer_id'] ?? null,
                'fresh_iss_binding_hash' => $iss['binding_hash'], 'declared_relationship' => 'NO_RELATIONSHIP',
                'declared_support_mechanisms' => 'NONE_FOR_BOTH_PARTIES', 'temporary_goods_movement' => false,
                'mdic_information_authorized' => false, 'trade_fields' => $trade, 'performance_claim' => $performance];
            if (CanonicalJson::encode($value) !== CanonicalJson::encode($expected)) {
                return $this->stop('CURRENT_DOCUMENT_COMEX_CONTEXT_CONFLICT');
            }
            $fields = [];
            foreach ($trade as $key => $item) {
                $fields['serv.comExt.'.$key] = $item;
            }
            $facets = (new DpsIssuerFieldValidator)->validate($fields, ['xsd_source_hash' => $codes['dps_xsd']['source_sha256'] ?? null], $codes);
            if ($facets['status'] !== 'VALID') {
                return $this->stop('OFFICIAL_COMEX_FACETS_CONFLICT');
            }
            $origin = $proof['origin'];
            $body = array_diff_key($proof, ['origin' => true, 'source_hash' => true]);
            if (! is_array($origin) || count($origin) !== 4 || ! is_string($origin['reference'] ?? null) || trim($origin['reference']) === ''
                || ($origin['media_type'] ?? null) !== 'application/json' || ($origin['content'] ?? null) !== CanonicalJson::encode($body)
                || ($origin['content_sha256'] ?? null) !== hash('sha256', $origin['content'])
                || $proof['source_hash'] !== CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]))) {
                return $this->stop('COMEX_SOURCE_CONTENT_OR_HASH_CONFLICT');
            }
            $rows[] = ['role' => $doc['role'], 'document_reference' => $doc['document_reference'], 'trade_fields' => $trade,
                'performance_claim' => $performance, 'proof_hash' => $proof['source_hash'], 'source_evidence_id' => $proof['evidence_id']];
        }
        if ($rows[0]['source_evidence_id'] === $rows[1]['source_evidence_id']) {
            return $this->stop('INDEPENDENT_DOCUMENT_COMEX_PROOFS_REQUIRED');
        }

        return ['status' => 'LOCAL_DOCUMENT_COMEX_CLAIMS_BOUND', 'documents' => $rows,
            'binding_hash' => CanonicalJson::hash(['iss' => $iss['binding_hash'], 'documents' => $rows, 'known_at' => $known->format('Y-m-d\TH:i:s.uP')]),
            'mode_inferred_from_rtc_role' => false, 'proof_authenticated' => false, 'currency_temporal_validity_proven' => false,
            'all_comex_profiles_supported' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value)) {
            return null;
        }
        $format = str_contains($value, '.') ? 'Y-m-d\TH:i:s.uP' : 'Y-m-d\TH:i:sP';
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value ? $date : null;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'COMEX135_'.$reason,
            'document_obligation_complete' => false, 'production_ready' => false];
    }
}
