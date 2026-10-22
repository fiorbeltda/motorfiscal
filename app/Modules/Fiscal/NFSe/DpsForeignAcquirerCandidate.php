<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DpsForeignPolicy128;
use App\Modules\Fiscal\ConsumptionReform\DpsIssuerFieldValidator;
use App\Modules\Fiscal\ConsumptionReform\DpsLayoutPolicy127;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;

/** Local XML assembly binding only; this does not prove COMEX mode, identity or currency validity. */
final readonly class DpsForeignAcquirerCandidate
{
    public const CONTRACT = 'DPS-LOCAL-FOREIGN-ACQUIRER-CANDIDATE-2026.10.15-76';

    /**
     * @param  array<string, mixed>  $facts
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $codes
     * @return array<string, mixed>
     */
    public function bind(array $facts, array $identity, array $codes): array
    {
        $policy = $codes['foreign_acquirer_candidate'] ?? null;
        $modern = DpsForeignPolicy128::required($codes);
        if (! in_array($codes['local_xml_projection']['contract_version'] ?? null,
            $modern ? [DpsForeignPolicy128::CONTRACT] : [self::CONTRACT], true)
            || ! is_array($policy) || ($policy['scope'] ?? null) !== 'LOCAL_STRUCTURAL_CANDIDATE_ONLY'
            || ($policy['currency_competence_validity_proven'] ?? null) !== false
            || ! is_string($policy['currency_code_source_sha256'] ?? null)
            || preg_match('/\A[a-f0-9]{64}\z/D', $policy['currency_code_source_sha256']) !== 1) {
            return $this->stop('ACTION_REQUIRED', 'CONTRACT_NOT_PROVEN');
        }
        if (($identity['environment'] ?? null) !== '2') {
            return $this->stop('ACTION_REQUIRED', 'PRODUCTION_ENVIRONMENT_FORBIDDEN');
        }
        $observedText = $policy['currency_code_observed_at'] ?? null;
        $issuedText = $identity['issued_at'] ?? null;
        $observed = is_string($observedText) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $observedText) : false;
        $issued = is_string($issuedText) ? DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $issuedText) : false;
        if ($observed === false || $issued === false
            || $observed->format('Y-m-d\TH:i:s.uP') !== $observedText
            || $issued->format('Y-m-d\TH:i:sP') !== $issuedText) {
            return $this->stop('ACTION_REQUIRED', 'EXACT_OBSERVATION_AND_ISSUANCE_REQUIRED');
        }
        if ($issued < $observed) {
            return $this->stop('ACTION_REQUIRED', 'CURRENCY_ARTIFACT_NOT_YET_OBSERVED');
        }
        if ($modern) {
            foreach ([(new DpsForeignPolicy128)->qualify($codes, $issued), (new DpsLayoutPolicy127)->qualify($codes, $issued)] as $qualification) {
                if ($qualification['status'] !== 'RESOLVED') {
                    return $qualification;
                }
            }
        }
        if (($facts['rtc.market'] ?? null) !== 'DOMESTIC'
            || ($facts['rtc.recipient_country'] ?? null) !== 'BR'
            || ($facts['rtc.destination_basis'] ?? null) !== 'RECIPIENT_DOMICILE'
            || ($facts['rtc.acquirer_country'] ?? null) === 'BR') {
            return $this->stop('ACTION_REQUIRED', 'DOMESTIC_RECIPIENT_ROUTE_REQUIRED');
        }
        $party = $identity['foreign_acquirer'] ?? null;
        if (! is_array($party)) {
            return $this->stop('ACTION_REQUIRED', 'FOREIGN_PARTY_REQUIRED');
        }
        if (! is_string($facts['rtc.acquirer_id'] ?? null) || ! is_string($facts['rtc.document_id'] ?? null)
            || ($identity['foreign_acquirer_id'] ?? null) !== $facts['rtc.acquirer_id']
            || ($identity['foreign_document_id'] ?? null) !== $facts['rtc.document_id']
            || ($party['country'] ?? null) !== ($facts['rtc.acquirer_country'] ?? null)
            || ($party['name'] ?? null) !== ($identity['acquirer_name'] ?? null)
            || (isset($facts['rtc.acquirer_foreign_nif']) && ($party['nif'] ?? null) !== $facts['rtc.acquirer_foreign_nif'])) {
            return $this->stop('INVALID', 'PARTY_OR_DOCUMENT_BINDING_CONFLICT');
        }
        $projected = (new DpsForeignPartyProjector)->project($party, 'toma', $codes, $modern ? $issued : null);
        if ($projected['status'] !== 'PROJECTED') {
            return $projected;
        }
        $trade = $identity['foreign_trade'] ?? null;
        $evidence = $identity['foreign_trade_evidence_id'] ?? null;
        if (! is_array($trade) || ! is_string($evidence) || trim($evidence) === '') {
            return $this->stop('ACTION_REQUIRED', 'COMEXT_FIELDS_AND_EVIDENCE_REQUIRED');
        }
        $order = ['mdPrestacao', 'vincPrest', 'tpMoeda', 'vServMoeda', 'mecAFComexP', 'mecAFComexT',
            'movTempBens', 'nDI', 'nRE', 'mdic'];
        if (array_diff(array_keys($trade), $order) !== []) {
            return $this->stop('ACTION_REQUIRED', 'ADDITIONAL_COMEXT_FIELDS_NOT_MAPPED');
        }
        foreach (array_diff($order, ['nDI', 'nRE']) as $field) {
            if (! isset($trade[$field]) || ! is_string($trade[$field]) || trim($trade[$field]) === '') {
                return $this->stop('ACTION_REQUIRED', 'COMEXT_REQUIRED_FIELD_MISSING');
            }
        }
        $currency = $facts['rtc.gross_currency'] ?? null;
        $mapping = is_string($currency) ? ($policy['observed_currency_codes'][$currency] ?? null) : null;
        if (! is_string($mapping) || ($trade['tpMoeda'] ?? null) !== $mapping
            || ($trade['vServMoeda'] ?? null) !== ($facts['rtc.gross_amount'] ?? null)) {
            return $this->stop('INVALID', 'ORIGINAL_CURRENCY_OR_AMOUNT_CONFLICT');
        }
        if (($trade['movTempBens'] === '2') !== isset($trade['nDI'])
            || ($trade['movTempBens'] === '3') !== isset($trade['nRE'])) {
            return $this->stop('INVALID', 'TEMPORARY_GOODS_DOCUMENT_CONFLICT');
        }
        $fields = [];
        foreach ($order as $field) {
            if (array_key_exists($field, $trade)) {
                $fields['serv.comExt.'.$field] = $trade[$field];
            }
        }
        $validation = (new DpsIssuerFieldValidator)->validate($fields,
            ['xsd_source_hash' => $modern ? DpsLayoutPolicy127::SOURCE_SHA256 : DpsRestrictedSchemaSelection::ARCHIVE_SHA256], $codes);
        if ($validation['status'] !== 'VALID') {
            return [...$this->stop('INVALID', 'COMEXT_SOURCE_FACET_CONFLICT'), 'field_validation' => $validation];
        }

        return ['status' => 'PROJECTED', 'reason' => 'RTC_DPS_FOREIGN_ACQUIRER_LOCAL_BINDING_BUILT',
            'scope' => 'LOCAL_STRUCTURAL_CANDIDATE_ONLY', 'party_xml' => $projected['xml'], 'trade_fields' => $fields,
            'party_input_hash' => $projected['party_input_hash'], 'party_evidence_links' => $projected['evidence_links'],
            'identity_input_hash' => CanonicalJson::hash($identity), 'trade_evidence_id' => $evidence,
            'currency_code_source_sha256' => $policy['currency_code_source_sha256'] ?? null,
            'currency_code_observed_at' => $observedText,
            'country_table_validity_qualified' => false, 'currency_competence_validity_proven' => false,
            'comext_mode_fiscally_qualified' => false, 'identity_authenticity_verified' => false,
            'document_obligation_complete' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => 'RTC_DPS_FOREIGN_ACQUIRER_'.$reason,
            'xml' => null, 'production_ready' => false];
    }
}
