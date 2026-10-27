<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DpsIssuerFieldValidator;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use Throwable;

/** Complete local DPS assembly for the qualified141/142 profile, retaining observed schema and application refusals. */
final readonly class ImportDpsModel143
{
    public const CONTRACT = 'IMPORT-DPS-NT009-MODEL-143-V1';

    public const AVAILABLE_AT = '2026-10-27T13:23:41.019734+00:00';

    public const PROFILE_SHA256 = 'f28ac084591e53ff7017c00d1bead5ba82f60e15b4553103c365d44881f2eed0';

    /** @param array<string,mixed> $snapshot Replayed exact141 snapshot.
     * @param  array<string,mixed>  $invoiceEvidence
     * @param  array<string,mixed>  $comext
     * @param  array<string,mixed>  $identity
     * @return array<string,mixed>
     */
    public function project(array $snapshot, string $invoice, array $invoiceEvidence, array $comext, array $identity, string $sourceRoot, string $profilePath): array
    {
        if (! is_file($profilePath) || hash_file('sha256', $profilePath) !== self::PROFILE_SHA256) {
            return $this->stop('EXACT_SOURCE_PROFILE_REQUIRED');
        }
        $profile = json_decode((string) file_get_contents($profilePath), true, 512, JSON_THROW_ON_ERROR);
        if (! is_file($sourceRoot.'/'.$profile['source_file']) || hash_file('sha256', $sourceRoot.'/'.$profile['source_file']) !== $profile['source_sha256']) {
            return $this->stop('ANEXOVI_PRIMARY_BYTES_REQUIRED');
        }
        $model = (new ImportNt009Model142)->project($snapshot, $invoice, $invoiceEvidence, $comext, $sourceRoot);
        if (($model['status'] ?? null) !== 'FIELD_MODEL_PREPARED_OBSERVED_SCHEMA_INCOMPATIBLE') {
            return $model;
        }
        $codes = $snapshot['knowledge']['documents']['rtc_code_catalog'];
        $codes['import_candidate_policy130'] = DpsImportCandidatePolicy130::policy();
        $knownText = $snapshot['input']['knowledge_at'];
        $selection = (new DpsRestrictedSchemaSelection127)->qualify($identity, $codes, $knownText);
        if ($selection['status'] !== 'VALID') {
            return $selection;
        }
        $headerFacets = (new DpsIssuerFieldValidator)->validate(
            ['dhEmi' => $identity['issued_at'], 'verAplic' => $identity['application_version'] ?? null],
            ['xsd_source_hash' => $codes['dps_xsd']['source_sha256']], $codes);
        if ($headerFacets['status'] !== 'VALID') {
            return $headerFacets;
        }
        $known = new DateTimeImmutable($knownText);
        if ($known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('CONTRACT_NOT_KNOWN');
        }
        $keys = ['contract', 'environment', 'issued_at', 'application_version', 'series', 'number', 'competence_date',
            'issuer_municipality', 'issuer_cnpj', 'acquirer_name', 'service_municipality', 'total_tax_indicator',
            'identity_evidence_id', 'service_location_evidence_id', 'total_tax_evidence_id', 'foreign_supplier',
            'acquirer_address', 'legacy_tax_document', 'final_consumption_indicator', 'observed_at'];
        if (count($identity) !== count($keys) || array_diff(array_keys($identity), $keys) !== []) {
            return $this->stop('EXACT_IDENTITY_REQUIRED');
        }
        foreach (array_diff($keys, ['foreign_supplier', 'acquirer_address', 'legacy_tax_document']) as $key) {
            if (! is_string($identity[$key]) || trim($identity[$key]) === '') {
                return $this->stop('EXPLICIT_IDENTITY_AND_EVIDENCE_REQUIRED');
            }
        }
        $observed = $this->instant($identity['observed_at']);
        if ($observed === null || $observed > new DateTimeImmutable($identity['issued_at'])) {
            return $this->stop('IDENTITY_NOT_KNOWN_AT_ISSUANCE');
        }
        $facts = $snapshot['input']['facts'];
        $outer = [];
        foreach ($snapshot['result']['trace'] as $row) {
            if (in_array($row['type'], ['ISS_CALCULATION', 'ISS_RESPONSIBILITY', 'ISS_LOCATION', 'CONSUMPTION_REFORM'], true)) {
                if (isset($outer[$row['type']])) {
                    return $this->stop('AMBIGUOUS_FISCAL_TRACE');
                }
                $outer[$row['type']] = $row['payload'];
            }
        }
        if (! isset($outer['ISS_CALCULATION'], $outer['ISS_RESPONSIBILITY'], $outer['ISS_LOCATION'], $outer['CONSUMPTION_REFORM'])) {
            return $this->stop('COMPLETE_FISCAL_TRACES_REQUIRED');
        }
        if (($identity['contract'] ?? null) !== self::CONTRACT || $identity['competence_date'] !== $snapshot['input']['competence_date']
            || $identity['issuer_cnpj'] !== ($facts['rtc.importer_id'] ?? null)
            || $identity['issuer_cnpj'] !== ($facts['rtc.acquirer_id'] ?? null)
            || preg_match('/\A[A-Z0-9]{12}[0-9]{2}\z/', $identity['issuer_cnpj']) !== 1
            || preg_match('/\A[1-9][0-9]{0,14}\z/', $identity['number']) !== 1 || (int) $identity['series'] === 0
            || $identity['issuer_municipality'] !== ($facts['customer.municipality_ibge'] ?? null)
            || $identity['issuer_municipality'] !== ($outer['ISS_LOCATION']['municipality_ibge'] ?? null)
            || $identity['service_municipality'] !== ($facts['rtc.performance_municipality_ibge'] ?? null)
            || $identity['total_tax_indicator'] !== '0' || ! in_array($identity['final_consumption_indicator'], ['0', '1'], true)
            || ($facts['operation.issuer_role'] ?? null) !== 'CUSTOMER' || ($facts['federal.evaluate_withholdings'] ?? null) !== false
            || ($outer['ISS_RESPONSIBILITY']['tpRetISSQN'] ?? null) !== '2'
            || ($outer['ISS_RESPONSIBILITY']['responsible_party'] ?? null) !== 'CUSTOMER') {
            return $this->stop('IDENTITY_OR_RESPONSIBILITY_BINDING_CONFLICT');
        }
        $supplier = $identity['foreign_supplier'];
        if (! is_array($supplier) || ($supplier['nif'] ?? null) !== ($facts['rtc.supplier_foreign_nif'] ?? null)
            || ($supplier['country'] ?? null) !== ($facts['rtc.supplier_country'] ?? null)) {
            return $this->stop('SUPPLIER_BINDING_CONFLICT');
        }
        $party = (new DpsForeignPartyProjector)->project($supplier, 'prest', $codes, $known);
        if ($party['status'] !== 'PROJECTED') {
            return $party;
        }
        $address = $identity['acquirer_address'];
        $addressKeys = ['municipality', 'postal_code', 'street', 'number', 'district', 'evidence_id'];
        if (! is_array($address) || count($address) !== count($addressKeys) || array_diff(array_keys($address), $addressKeys) !== []
            || ($address['municipality'] ?? null) !== ($facts['rtc.destination_municipality_ibge'] ?? null)
            || ($address['municipality'] ?? null) !== $identity['issuer_municipality']
            || ! is_string($address['postal_code'] ?? null) || preg_match('/\A[0-9]{8}\z/', $address['postal_code']) !== 1
            || ! is_string($address['evidence_id'] ?? null) || trim($address['evidence_id']) === '') {
            return $this->stop('EXPLICIT_ACQUIRER_ADDRESS_REQUIRED');
        }
        $legacy = $identity['legacy_tax_document'];
        if (! is_array($legacy) || count($legacy) !== 2 || array_diff(array_keys($legacy), ['cst', 'evidence_id']) !== []
            || ($legacy['cst'] ?? null) !== '99'
            || ! is_string($legacy['evidence_id'] ?? null) || trim($legacy['evidence_id']) === '') {
            return $this->stop('EXPLICIT_LEGACY_DOCUMENT_CST_AND_EVIDENCE_REQUIRED');
        }
        $services = array_values(array_filter($snapshot['knowledge']['documents']['service_catalog']['services'],
            static fn (array $row): bool => $row['id'] === $facts['service.canonical_id']));
        if (count($services) !== 1 || ($services[0]['dps_detail_group'] ?? null) !== null) {
            return $this->stop('SERVICE_DETAIL_NOT_MAPPED');
        }
        $partial = null;
        foreach ($outer['CONSUMPTION_REFORM']['trace'] as $stage) {
            if ($stage['stage'] === 'IMPORT_LOCAL_DPS_PROJECTION') {
                if ($partial !== null) {
                    return $this->stop('AMBIGUOUS_DOCUMENT_PROJECTION');
                }
                $partial = $stage['issuer_fields'];
            }
        }
        if (! is_array($partial)) {
            return $this->stop('RTC_DOCUMENT_PROJECTION_REQUIRED');
        }
        $id = 'DPS'.$identity['issuer_municipality'].'2'.$identity['issuer_cnpj']
            .str_pad($identity['series'], 5, '0', STR_PAD_LEFT).str_pad($identity['number'], 15, '0', STR_PAD_LEFT);
        $fields = ['DPS.@versao' => '1.01', 'DPS.infDPS.@Id' => $id];
        foreach (['tpAmb' => '2', 'dhEmi' => $identity['issued_at'], 'verAplic' => $identity['application_version'],
            'serie' => $identity['series'], 'nDPS' => $identity['number'], 'dCompet' => $identity['competence_date'],
            'tpEmit' => '2', 'cMotivoEmisTI' => '1', 'finNFSe' => '0', 'cLocEmi' => $identity['issuer_municipality'], 'indDest' => '0'] as $tag => $value) {
            $fields['DPS.infDPS.'.$tag] = $value;
        }
        foreach ($party['fields'] as $path => $value) {
            $fields['DPS.infDPS.'.$path] = $value;
        }
        $fields += ['DPS.infDPS.toma.CNPJ' => $identity['issuer_cnpj'], 'DPS.infDPS.toma.xNome' => $identity['acquirer_name'],
            'DPS.infDPS.toma.end.endNac.cMun' => $address['municipality'], 'DPS.infDPS.toma.end.endNac.CEP' => $address['postal_code'],
            'DPS.infDPS.toma.end.xLgr' => $address['street'], 'DPS.infDPS.toma.end.nro' => $address['number'],
            'DPS.infDPS.toma.end.xBairro' => $address['district'], 'DPS.infDPS.serv.locPrest.cLocPrestacao' => $identity['service_municipality'],
            'DPS.infDPS.serv.cServ.cTribNac' => $services[0]['ctribnac_code'], 'DPS.infDPS.serv.cServ.cNBS' => str_replace('.', '', $facts['rtc.service_nbs_code']),
            'DPS.infDPS.serv.cServ.xDescServ' => $services[0]['description']];
        $fields += $model['fields'];
        $taxes = array_column($model['monetary_reconciliation']['legacy_taxes'], 'amount', 'tax');
        $fields += ['DPS.infDPS.valores.trib.tribMun.tribISSQN' => '1', 'DPS.infDPS.valores.trib.tribMun.tpRetISSQN' => '2',
            'DPS.infDPS.valores.trib.tribMun.pAliq' => $outer['ISS_CALCULATION']['rate'],
            'DPS.infDPS.valores.trib.tribFed.piscofins.CST' => $legacy['cst'],
            'DPS.infDPS.valores.trib.tribFed.piscofins.vPis' => $taxes['PIS'], 'DPS.infDPS.valores.trib.tribFed.piscofins.vCofins' => $taxes['COFINS'],
            'DPS.infDPS.valores.trib.totTrib.indTotTrib' => '0', 'DPS.infDPS.IBSCBS.indFinal' => $identity['final_consumption_indicator'],
            'DPS.infDPS.IBSCBS.cIndOp' => $partial['DPS.infDPS.IBSCBS.cIndOp'],
            'DPS.infDPS.IBSCBS.valores.trib.CST' => $partial['DPS.infDPS.IBSCBS.valores.trib.CST'],
            'DPS.infDPS.IBSCBS.valores.trib.cClassTrib' => $partial['DPS.infDPS.IBSCBS.valores.trib.cClassTrib']];
        $structure = (new AnexoViStructure143)->validate($fields, $profile);
        if ($structure['status'] !== 'OBSERVED_TABLE_STRUCTURE_CONFORMS') {
            return $structure;
        }
        uksort($fields, static fn (string $a, string $b): int => $profile['rows'][$a]['source_row'] <=> $profile['rows'][$b]['source_row']);
        $xml = $this->serialize($fields);
        $validation = (new DpsXmlSchemaValidator)->validate($xml, $sourceRoot.'/'.$codes['dps_xsd']['source_file'],
            $codes['dps_xsd']['source_sha256'], $codes['dps_xsd']['root_schema']);

        return ['status' => 'LOCAL_COMPLETE_PROFILE_DPS_ASSEMBLED_NOT_OFFICIALLY_ACCEPTED', 'contract' => self::CONTRACT,
            'scope' => 'COMPLETE_LOCAL_DPS_ASSEMBLY_FOR_EXPLICIT_IMPORT141_PROFILE', 'candidate_xml' => $xml, 'candidate_xml_sha256' => hash('sha256', $xml),
            'fields' => $fields, 'source_profile_sha256' => self::PROFILE_SHA256, 'table_structure_validation' => $structure,
            'common_header_facet_validation' => $headerFacets,
            'official_xsd_validation' => $validation, 'nt009_schema_coverage' => $model['schema_coverage'],
            'application_business_rule_gate' => ['status' => 'OBSERVED_APPLICATION_REJECTS_CUSTOMER_ISSUANCE', 'code' => 'E9996', 'source_sheet' => 'RN DPS_NFS-e', 'source_row' => 230,
                'companion_import_issuer_rule' => ['code' => 'E0027', 'source_row' => 231], 'source_sha256' => $profile['source_sha256'], 'fiscal_obligation_overridden' => false],
            'monetary_reconciliation' => $model['monetary_reconciliation'], 'identity_sha256' => CanonicalJson::hash($identity),
            'legacy_document_cst_declared' => $legacy, 'legacy_tax_assessment_performed' => false, 'identity_authenticity_verified' => false,
            'xml_generated' => true, 'xml_accepted_by_official_schema' => false, 'signed' => false, 'transmitted' => false,
            'document_obligation_complete' => false, 'fiscal_decision_final' => false, 'production_ready' => false];
    }

    /** @param array<string,string> $fields */
    private function serialize(array $fields): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'DPS');
        $doc->appendChild($root);
        /** @var array<string,DOMElement> $nodes */
        $nodes = ['DPS' => $root];
        foreach ($fields as $path => $value) {
            $parts = explode('.', $path);
            array_shift($parts);
            $parent = 'DPS';
            while ($parts !== []) {
                $part = array_shift($parts);
                if (str_starts_with($part, '@')) {
                    $nodes[$parent]->setAttribute(substr($part, 1), $value);
                    break;
                }
                $next = $parent.'.'.$part;
                if (! isset($nodes[$next])) {
                    $nodes[$next] = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', $part);
                    $nodes[$parent]->appendChild($nodes[$next]);
                }
                if ($parts === []) {
                    $nodes[$next]->appendChild($doc->createTextNode($value));
                }
                $parent = $next;
            }
        }

        return $doc->saveXML() ?: '';
    }

    private function instant(string $value): ?DateTimeImmutable
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})\z/', $value) !== 1) {
            return null;
        }
        try {
            $date = new DateTimeImmutable($value);

            return DateTimeImmutable::getLastErrors() === false ? $date : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'IMPORT_DPS143_'.$reason, 'xml_generated' => false,
            'document_obligation_complete' => false, 'fiscal_decision_final' => false, 'production_ready' => false];
    }
}
