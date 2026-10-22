<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DpsIssuerFieldValidator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;

/** Consumes a replayed nonfinal import assessment; never creates a final fiscal decision. */
final readonly class DpsImportCandidate130
{
    /** @param array<string, mixed> $snapshot
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function project(array $snapshot, array $identity, array $policy): array
    {
        $codes = $snapshot['knowledge']['documents']['rtc_code_catalog'] ?? [];
        $codes['import_candidate_policy130'] = $policy;
        $knownText = $snapshot['input']['knowledge_at'] ?? '';
        $known = is_string($knownText) ? DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $knownText) : false;
        if ($known === false || (new DpsImportCandidatePolicy130)->qualify($codes, $known)['status'] !== 'RESOLVED') {
            return $this->stop('SOURCE_POLICY_NOT_BOUND');
        }
        $result = $snapshot['result'] ?? [];
        if (($result['status'] ?? null) !== 'ACTION_REQUIRED'
            || ($result['reason_codes'] ?? null) !== ['RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN']
            || ($codes['version'] ?? null) !== '2026.10.22-129-draft1') {
            return $this->stop('EXACT_NONFINAL_IMPORT129_REQUIRED');
        }
        $outer = [];
        foreach ($result['trace'] ?? [] as $item) {
            if (isset($outer[$item['type']])) {
                // Execution-plan events repeat; fiscal payloads used below must occur once.
                if (in_array($item['type'], ['CONSUMPTION_REFORM', 'ISS_CALCULATION', 'ISS_RESPONSIBILITY', 'ISS_LOCATION'], true)) {
                    return $this->stop('AMBIGUOUS_FISCAL_TRACE', 'INVALID');
                }
            }
            $outer[$item['type']] = $item['payload'];
        }
        $rtc = $outer['CONSUMPTION_REFORM'] ?? [];
        $stages = [];
        foreach ($rtc['trace'] ?? [] as $stage) {
            if (isset($stages[$stage['stage']])) {
                return $this->stop('AMBIGUOUS_RTC_TRACE', 'INVALID');
            }
            $stages[$stage['stage']] = $stage;
        }
        $responsibility = $stages['IMPORT_RESPONSIBILITY129'] ?? [];
        $partial = $stages['IMPORT_LOCAL_DPS_PROJECTION'] ?? [];
        $iss = $outer['ISS_CALCULATION'] ?? [];
        $facts = $snapshot['input']['facts'] ?? [];
        if (($responsibility['status'] ?? null) !== 'RESOLVED' || ($partial['scope'] ?? null) !== 'PARTIAL_SANDBOX_NO_EMISSION'
            || ($facts['rtc.market'] ?? null) !== 'IMPORT' || ($facts['rtc.operation_kind'] ?? null) !== 'DIGITAL'
            || ($facts['rtc.delivery_mode'] ?? null) !== 'DIGITAL' || ($facts['rtc.acquirer_country'] ?? null) !== 'BR'
            || ($facts['rtc.acquirer_id'] ?? null) !== ($facts['rtc.recipient_id'] ?? null)
            || ($facts['rtc.import_brazil_share_percent'] ?? null) !== '100'
            || ($facts['rtc.gross_currency'] ?? null) !== 'BRL' || ($facts['rtc.base_adjustments'] ?? null) !== []
            || ($iss['valid'] ?? null) !== true || ($iss['currency'] ?? null) !== 'BRL'
            || ($iss['deduction_amount'] ?? null) !== '0.00'
            || ($outer['ISS_RESPONSIBILITY']['tpRetISSQN'] ?? null) !== '2'
            || ($outer['ISS_RESPONSIBILITY']['responsible_party'] ?? null) !== 'CUSTOMER'
            || ($facts['operation.issuer_role'] ?? null) !== 'CUSTOMER'
            || ($facts['federal.evaluate_withholdings'] ?? null) !== false) {
            return $this->stop('DOCUMENT_PROFILE_NOT_MAPPED');
        }
        $selection = (new DpsRestrictedSchemaSelection127)->qualify($identity, $codes, $knownText);
        if ($selection['status'] !== 'VALID') {
            return $selection;
        }
        $allowed = ['environment', 'issued_at', 'application_version', 'series', 'number', 'competence_date',
            'issuer_municipality', 'issuer_cnpj', 'acquirer_name', 'service_municipality', 'total_tax_indicator',
            'identity_evidence_id', 'service_location_evidence_id', 'total_tax_evidence_id', 'foreign_supplier',
            'foreign_trade', 'foreign_trade_evidence_id'];
        if (array_diff(array_keys($identity), $allowed) !== []) {
            return $this->stop('ADDITIONAL_IDENTITY_FIELDS_NOT_MAPPED');
        }
        foreach (array_diff($allowed, ['foreign_supplier', 'foreign_trade']) as $key) {
            if (! is_string($identity[$key] ?? null) || trim($identity[$key]) === '') {
                return $this->stop('EXPLICIT_IDENTITY_REQUIRED');
            }
        }
        if ($identity['competence_date'] !== ($snapshot['input']['competence_date'] ?? null)
            || $identity['issuer_cnpj'] !== ($facts['rtc.importer_id'] ?? null)
            || preg_match('/\A[0-9]{14}\z/', $identity['issuer_cnpj']) !== 1
            || preg_match('/\A[1-9][0-9]{0,14}\z/', $identity['number']) !== 1
            || $identity['issuer_municipality'] !== ($facts['customer.municipality_ibge'] ?? null)
            || $identity['issuer_municipality'] !== ($outer['ISS_LOCATION']['municipality_ibge'] ?? null)
            || $identity['service_municipality'] !== ($facts['rtc.performance_municipality_ibge'] ?? null)
            || preg_match('/\A[0-9]{7}\z/', $identity['service_municipality']) !== 1
            || $identity['total_tax_indicator'] !== '0') {
            return $this->stop('IDENTITY_FACT_BINDING_CONFLICT', 'INVALID');
        }
        $nbs = $facts['rtc.service_nbs_code'] ?? null;
        if (! is_string($nbs) || preg_match('/\A[0-9]\.[0-9]{4}\.[0-9]{2}\.[0-9]{2}\z/', $nbs) !== 1
            || ! in_array($nbs, array_map(static fn (int|string $key): string => trim((string) $key), array_keys($codes['nbs'] ?? [])), true)
            || ! is_string($facts['rtc.service_nbs_evidence_id'] ?? null) || trim($facts['rtc.service_nbs_evidence_id']) === '') {
            return $this->stop('EXPLICIT_NBS_AND_EVIDENCE_REQUIRED');
        }
        $supplier = $identity['foreign_supplier'] ?? null;
        if (! is_array($supplier) || ($supplier['country'] ?? null) !== ($facts['rtc.supplier_country'] ?? null)
            || ($supplier['nif'] ?? null) !== ($facts['rtc.supplier_foreign_nif'] ?? null)) {
            return $this->stop('SUPPLIER_FACT_BINDING_CONFLICT', 'INVALID');
        }
        $party = (new DpsForeignPartyProjector)->project($supplier, 'prest', $codes, $known);
        if ($party['status'] !== 'PROJECTED') {
            return $party;
        }
        $trade = $identity['foreign_trade'] ?? null;
        $order = ['mdPrestacao', 'vincPrest', 'tpMoeda', 'vServMoeda', 'mecAFComexP', 'mecAFComexT', 'movTempBens', 'mdic'];
        if (! is_array($trade) || count($trade) !== count($order) || array_diff(array_keys($trade), $order) !== []) {
            return $this->stop('EXACT_COMEXT_FIELDS_REQUIRED');
        }
        if (($trade['tpMoeda'] ?? null) !== '790' || ($trade['vServMoeda'] ?? null) !== ($facts['rtc.gross_amount'] ?? null)
            || ($trade['movTempBens'] ?? null) !== '1') {
            return $this->stop('ORIGINAL_AMOUNT_CURRENCY_OR_GOODS_CONFLICT', 'INVALID');
        }
        $fields = ['verAplic' => $identity['application_version']];
        foreach ($order as $field) {
            if (! is_string($trade[$field])) {
                return $this->stop('COMEXT_EXACT_TEXT_REQUIRED', 'INVALID');
            }
            $fields['serv.comExt.'.$field] = $trade[$field];
        }
        $validation = (new DpsIssuerFieldValidator)->validate($fields, ['xsd_source_hash' => $codes['dps_xsd']['source_sha256']], $codes);
        if ($validation['status'] !== 'VALID') {
            return $this->stop('COMEXT_OR_APPLICATION_FACET_CONFLICT', 'INVALID');
        }
        $services = array_values(array_filter($snapshot['knowledge']['documents']['service_catalog']['services'] ?? [],
            static fn (array $row): bool => $row['id'] === ($facts['service.canonical_id'] ?? null)));
        if (count($services) !== 1 || ($services[0]['dps_detail_group'] ?? null) !== null) {
            return $this->stop('SERVICE_CLASSIFICATION_REQUIRED');
        }
        $issuerFields = $partial['issuer_fields'] ?? [];
        foreach (['DPS.infDPS.IBSCBS.cIndOp', 'DPS.infDPS.IBSCBS.valores.trib.CST', 'DPS.infDPS.IBSCBS.valores.trib.cClassTrib'] as $field) {
            if (! is_string($issuerFields[$field] ?? null)) {
                return $this->stop('IMPORT_ISSUER_FIELDS_REQUIRED');
            }
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'DPS');
        $root->setAttribute('versao', '1.01');
        $doc->appendChild($root);
        $info = $this->node($doc, $root, 'infDPS');
        $info->setAttribute('Id', 'DPS'.$identity['issuer_municipality'].'2'.$identity['issuer_cnpj']
            .str_pad($identity['series'], 5, '0', STR_PAD_LEFT).str_pad($identity['number'], 15, '0', STR_PAD_LEFT));
        foreach (['tpAmb' => '2', 'dhEmi' => $identity['issued_at'], 'verAplic' => $identity['application_version'],
            'serie' => $identity['series'], 'nDPS' => $identity['number'], 'dCompet' => $identity['competence_date'],
            'tpEmit' => '2', 'cMotivoEmisTI' => '1', 'finNFSe' => '0', 'cLocEmi' => $identity['issuer_municipality']] as $tag => $value) {
            $this->node($doc, $info, $tag, $value);
        }
        $fragment = new DOMDocument;
        if (! $fragment->loadXML($party['xml'], LIBXML_NONET) || ! $fragment->documentElement instanceof DOMElement) {
            return $this->stop('FOREIGN_FRAGMENT_INVALID', 'INVALID');
        }
        $info->appendChild($doc->importNode($fragment->documentElement, true));
        $taker = $this->node($doc, $info, 'toma');
        $this->node($doc, $taker, 'CNPJ', $identity['issuer_cnpj']);
        $this->node($doc, $taker, 'xNome', $identity['acquirer_name']);
        $service = $this->node($doc, $info, 'serv');
        $this->node($doc, $this->node($doc, $service, 'locPrest'), 'cLocPrestacao', $identity['service_municipality']);
        $serviceCode = $this->node($doc, $service, 'cServ');
        foreach (['cTribNac' => $services[0]['ctribnac_code'], 'cNBS' => str_replace('.', '', $nbs), 'xDescServ' => $services[0]['description']] as $tag => $value) {
            $this->node($doc, $serviceCode, $tag, $value);
        }
        $comext = $this->node($doc, $service, 'comExt');
        foreach ($order as $tag) {
            $this->node($doc, $comext, $tag, $trade[$tag]);
        }
        $values = $this->node($doc, $info, 'valores');
        $this->node($doc, $this->node($doc, $values, 'vServPrest'), 'vServ', $iss['gross_amount']);
        $tax = $this->node($doc, $values, 'trib');
        $municipal = $this->node($doc, $tax, 'tribMun');
        $this->node($doc, $municipal, 'tribISSQN', '1');
        $this->node($doc, $municipal, 'tpRetISSQN', '2');
        $this->node($doc, $this->node($doc, $tax, 'totTrib'), 'indTotTrib', '0');
        $rtcXml = $this->node($doc, $info, 'IBSCBS');
        $this->node($doc, $rtcXml, 'cIndOp', $issuerFields['DPS.infDPS.IBSCBS.cIndOp']);
        $this->node($doc, $rtcXml, 'indDest', '0');
        $rtcTax = $this->node($doc, $this->node($doc, $rtcXml, 'valores'), 'trib');
        $this->node($doc, $rtcTax, 'CST', $issuerFields['DPS.infDPS.IBSCBS.valores.trib.CST']);
        $this->node($doc, $rtcTax, 'cClassTrib', $issuerFields['DPS.infDPS.IBSCBS.valores.trib.cClassTrib']);

        return ['status' => 'CANDIDATE_BUILT', 'reason' => 'RTC_IMPORT130_LOCAL_CANDIDATE_BUILT',
            'xml' => $doc->saveXML() ?: null, 'contract' => DpsImportCandidatePolicy130::CONTRACT,
            'fiscal_decision_status' => $result['status'], 'fiscal_decision_final' => false,
            'document_obligation_complete' => false, 'production_ready' => false,
            'snapshot_hash' => CanonicalJson::hash($snapshot), 'identity_hash' => CanonicalJson::hash($identity),
            'documentary_evidence' => $partial['evidence'], 'responsibility' => $responsibility,
            'iss_calculation' => $iss, 'rtc_nominal_calculation' => $stages['IMPORT_NOMINAL_CALCULATION'],
            'nbs_evidence_id' => $facts['rtc.service_nbs_evidence_id'],
            'unresolved' => $policy['unresolved']];
    }

    private function node(DOMDocument $doc, DOMElement $parent, string $tag, ?string $value = null): DOMElement
    {
        $node = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', $tag);
        if ($value !== null) {
            $node->appendChild($doc->createTextNode($value));
        }
        $parent->appendChild($node);

        return $node;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED'): array
    {
        return ['status' => $status, 'reason' => 'RTC_IMPORT130_'.$reason, 'xml' => null,
            'fiscal_decision_final' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }
}
