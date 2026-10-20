<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;

/** Builds a local DPS candidate from an integrated decision and explicit document identity. */
final readonly class DpsXmlProjector
{
    public const CONTRACT_VERSION = 'DPS-LOCAL-2026.10.01-1';

    public const NBS_CONTRACT_VERSION = 'DPS-LOCAL-2026.10.01-2';

    public const IMMUNITY_CONTRACT_VERSION = 'DPS-LOCAL-2026.10.02-1';

    /**
     * @param  array<string, mixed>  $decision
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>|null  $foreignCodes
     * @return array{status: string, reason: string, missing_fields: list<string>, xml: ?string, foreign_acquirer_binding?: array<string, mixed>}
     */
    public function project(array $decision, array $identity, ?array $foreignCodes = null): array
    {
        $projection = $decision['dps_projection'] ?? [];
        $issuerFields = $projection['rtc']['issuer_supplied'] ?? [];
        $required = ['environment', 'issued_at', 'application_version', 'series', 'number',
            'competence_date', 'issuer_municipality', 'supplier_cnpj', 'simples_option',
            'special_regime', 'total_tax_indicator', 'service_municipality', 'acquirer_name',
            'identity_evidence_id', 'service_location_evidence_id', 'regime_evidence_id', 'total_tax_evidence_id'];
        $missing = array_values(array_filter($required,
            static fn (string $key): bool => ! isset($identity[$key]) || ! is_string($identity[$key]) || trim($identity[$key]) === ''));
        if ($missing !== []) {
            return $this->result('ACTION_REQUIRED', 'RTC_DPS_IDENTITY_REQUIRED', $missing);
        }

        $immuneIss = ($decision['iss']['incidence']['value'] ?? null) === 'IMMUNE';
        if (! in_array($decision['iss']['incidence']['value'] ?? null, ['TAXABLE', 'IMMUNE'], true)
            || ($projection['service']['detail'] ?? []) !== []
            || ($decision['iss']['location']['municipality_ibge'] ?? null) === null
            || ($projection['rtc']['status'] ?? null) !== 'VALID') {
            return $this->result('ACTION_REQUIRED', 'RTC_DPS_XML_ROUTE_NOT_MAPPED');
        }
        if ($immuneIss) {
            $qualification = $decision['iss']['immunity_qualification'] ?? [];
            $type = $projection['iss']['tpImunidade'] ?? null;
            if (($qualification['status'] ?? null) !== 'RESOLVED' || ($qualification['layout_version'] ?? null) !== 'DPS_1.01'
                || ($projection['iss']['tribISSQN'] ?? null) !== '2' || ! in_array($type, ['2', '3'], true)
                || $type !== ($qualification['tpImunidade'] ?? null) || ($projection['iss']['tpRetISSQN'] ?? null) !== '1'
                || ($decision['iss']['calculation']['tax_amount'] ?? null) !== '0.00'
                || ($decision['iss']['rate']['rate'] ?? null) !== null) {
                return $this->result('INVALID', 'RTC_DPS_ISS_IMMUNITY_DECISION_CONFLICT');
            }
        }
        $fields = [
            'service_code' => $projection['service']['cTribNac'] ?? null,
            'service_description' => $projection['service']['xDescServ'] ?? null,
            'service_value' => $projection['values']['vServ'] ?? null,
            'iss_withholding' => $projection['iss']['tpRetISSQN'] ?? null,
            'service_municipality' => $identity['service_municipality'],
            'rtc_purpose' => $issuerFields['IBSCBS.finNFSe'] ?? null,
            'rtc_operation' => $issuerFields['IBSCBS.cIndOp'] ?? null,
            'rtc_destination' => $issuerFields['IBSCBS.indDest'] ?? null,
            'rtc_cst' => $issuerFields['IBSCBS.valores.trib.gIBSCBS.CST'] ?? null,
            'rtc_class' => $issuerFields['IBSCBS.valores.trib.gIBSCBS.cClassTrib'] ?? null,
        ];
        $missing = array_keys(array_filter($fields, static fn (mixed $value): bool => ! is_scalar($value) || (string) $value === ''));
        if ($missing !== []) {
            return $this->result('ACTION_REQUIRED', 'RTC_DPS_DECISION_FIELDS_REQUIRED', $missing);
        }
        if (! preg_match('/^[0-9]{14}$/', (string) $identity['supplier_cnpj'])
            || ! preg_match('/^[0-9]{7}$/', (string) $identity['issuer_municipality'])
            || ! preg_match('/^[0-9]{7}$/', (string) $identity['service_municipality'])
            || ! preg_match('/^[0-9]{1,5}$/', (string) $identity['series'])
            || ! preg_match('/^[1-9][0-9]{0,14}$/', (string) $identity['number'])
            || ! in_array((string) $identity['environment'], ['1', '2'], true)
            || ! in_array((string) $identity['simples_option'], ['1', '2', '3'], true)
            || ! in_array((string) $identity['special_regime'], ['0', '1', '2', '3', '4', '5', '6', '9'], true)
            || $identity['total_tax_indicator'] !== '0'
            || ! $this->dateMatches((string) $identity['competence_date'], 'Y-m-d')
            || ! $this->dateMatches((string) $identity['issued_at'], 'Y-m-d\TH:i:sP')
            || mb_strlen((string) $identity['application_version']) > 20) {
            return $this->result('INVALID', 'RTC_DPS_IDENTITY_FORMAT_INVALID');
        }
        $facts = [];
        foreach ($decision['facts_used'] ?? [] as $fact) {
            if (is_string($fact['key'] ?? null)) {
                $facts[$fact['key']] = $fact['value'] ?? null;
            }
        }
        // These values need documentary groups; projecting only vServ would lose their fiscal effect.
        $deduction = $decision['iss']['calculation']['deduction_amount'] ?? '0.00';
        $adjustments = $decision['consumption_reform']['decision']['base']['adjustments']
            ?? $facts['rtc.base_adjustments'] ?? [];
        if ($deduction !== '0.00' || $adjustments !== []
            || ($decision['iss']['treatment']['value'] ?? 'STANDARD') !== ($immuneIss ? 'IMMUNE' : 'STANDARD')
            || $identity['special_regime'] !== '0') {
            return $this->result('ACTION_REQUIRED', 'RTC_DPS_ADJUSTMENT_OR_SPECIAL_GROUP_REQUIRED');
        }
        if (($facts['rtc.supplier_id'] ?? null) !== $identity['supplier_cnpj']) {
            return $this->result('INVALID', 'RTC_DPS_SUPPLIER_MISMATCH');
        }
        if (($facts['provider.municipality_ibge'] ?? null) !== $identity['issuer_municipality']) {
            return $this->result('INVALID', 'RTC_DPS_ISSUER_MUNICIPALITY_MISMATCH');
        }
        if (isset($facts['rtc.performance_municipality_ibge']) && $facts['rtc.performance_municipality_ibge'] !== $identity['service_municipality']) {
            return $this->result('INVALID', 'RTC_DPS_PERFORMANCE_MUNICIPALITY_MISMATCH');
        }
        $nbs = $projection['service']['cNBS'] ?? null;
        if ($nbs !== null && (! is_string($nbs) || ! preg_match('/^[0-9]{9}$/', $nbs)
            || $nbs !== str_replace('.', '', (string) ($facts['rtc.service_nbs_code'] ?? ''))
            || ! is_string($facts['rtc.service_nbs_evidence_id'] ?? null))) {
            return $this->result('INVALID', 'RTC_DPS_NBS_DECISION_MISMATCH');
        }
        $simples = match ($facts['provider.tax_regime'] ?? null) {
            'LUCRO_REAL', 'LUCRO_PRESUMIDO' => '1',
            'MEI' => '2',
            'SIMPLES_NACIONAL' => '3',
            default => null,
        };
        if ($identity['simples_option'] !== $simples) {
            return $this->result('INVALID', 'RTC_DPS_REGIME_MISMATCH');
        }
        $acquirer = $facts['rtc.acquirer_id'] ?? null;
        $foreign = null;
        if ($foreignCodes !== null && ($facts['rtc.acquirer_country'] ?? null) !== 'BR') {
            if ($fields['rtc_destination'] !== '1') {
                return $this->result('INVALID', 'RTC_DPS_FOREIGN_ACQUIRER_SEPARATE_RECIPIENT_REQUIRED');
            }
            $foreign = (new DpsForeignAcquirerCandidate)->bind($facts, $identity, $foreignCodes);
            if ($foreign['status'] !== 'PROJECTED') {
                return $this->result($foreign['status'] === 'INVALID' ? 'INVALID' : 'ACTION_REQUIRED', (string) $foreign['reason']);
            }
        }
        if ($foreign === null && (! is_string($acquirer) || ! preg_match('/^(?:[0-9]{11}|[0-9]{14})$/', $acquirer)
            || ($facts['rtc.acquirer_country'] ?? null) !== 'BR')) {
            return $this->result('ACTION_REQUIRED', 'RTC_DPS_ACQUIRER_IDENTITY_REQUIRED', ['acquirer_identification']);
        }
        $recipient = $facts['rtc.recipient_id'] ?? null;
        if ($fields['rtc_destination'] === '1'
            && (! is_string($recipient) || ! preg_match('/^(?:[0-9]{11}|[0-9]{14})$/', $recipient)
                || ! is_string($identity['recipient_name'] ?? null) || trim($identity['recipient_name']) === '')) {
            return $this->result('ACTION_REQUIRED', 'RTC_DPS_SEPARATE_DESTINATION_REQUIRED', ['recipient_name']);
        }
        if (($fields['rtc_destination'] === '0' && $recipient !== $acquirer)
            || ($fields['rtc_destination'] === '1' && $recipient === $acquirer)) {
            return $this->result('INVALID', 'RTC_DPS_DESTINATION_ROLE_CONFLICT');
        }
        $federal = (new DpsFederalProjection)->project($decision['federal_withholdings'] ?? [], $identity);
        if ($federal['status'] !== 'PROJECTED') {
            return $this->result($federal['status'], $federal['reason'], $federal['missing_fields']);
        }
        $supported = ['IBSCBS.finNFSe', 'IBSCBS.cIndOp', 'IBSCBS.indDest',
            'IBSCBS.valores.trib.gIBSCBS.CST', 'IBSCBS.valores.trib.gIBSCBS.cClassTrib'];
        if (array_diff(array_keys($issuerFields), $supported) !== []) {
            return $this->result('ACTION_REQUIRED', 'RTC_DPS_ADDITIONAL_GROUP_REQUIRED');
        }
        $id = 'DPS'.$identity['issuer_municipality'].'2'.$identity['supplier_cnpj']
            .str_pad($identity['series'], 5, '0', STR_PAD_LEFT).str_pad($identity['number'], 15, '0', STR_PAD_LEFT);
        if (isset($identity['id']) && $identity['id'] !== $id) {
            return $this->result('INVALID', 'RTC_DPS_ID_COMPONENTS_MISMATCH');
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'DPS');
        $root->setAttribute('versao', '1.01');
        $doc->appendChild($root);
        $info = $this->node($doc, $root, 'infDPS');
        $info->setAttribute('Id', $id);
        foreach (['tpAmb' => 'environment', 'dhEmi' => 'issued_at', 'verAplic' => 'application_version',
            'serie' => 'series', 'nDPS' => 'number', 'dCompet' => 'competence_date'] as $tag => $key) {
            $this->node($doc, $info, $tag, (string) $identity[$key]);
        }
        $this->node($doc, $info, 'tpEmit', '1');
        $this->node($doc, $info, 'cLocEmi', (string) $identity['issuer_municipality']);
        $provider = $this->node($doc, $info, 'prest');
        $this->node($doc, $provider, 'CNPJ', (string) $identity['supplier_cnpj']);
        $regime = $this->node($doc, $provider, 'regTrib');
        $this->node($doc, $regime, 'opSimpNac', (string) $identity['simples_option']);
        $this->node($doc, $regime, 'regEspTrib', (string) $identity['special_regime']);
        if ($foreign !== null) {
            $partyDocument = new DOMDocument;
            if (! is_string($foreign['party_xml'] ?? null) || ! $partyDocument->loadXML($foreign['party_xml'], LIBXML_NONET)
                || ! $partyDocument->documentElement instanceof DOMElement) {
                return $this->result('INVALID', 'RTC_DPS_FOREIGN_PARTY_FRAGMENT_INVALID');
            }
            $info->appendChild($doc->importNode($partyDocument->documentElement, true));
        } else {
            $acquirerNode = $this->node($doc, $info, 'toma');
            $this->node($doc, $acquirerNode, strlen((string) $acquirer) === 11 ? 'CPF' : 'CNPJ', (string) $acquirer);
            $this->node($doc, $acquirerNode, 'xNome', $identity['acquirer_name']);
        }
        $service = $this->node($doc, $info, 'serv');
        $location = $this->node($doc, $service, 'locPrest');
        $this->node($doc, $location, 'cLocPrestacao', (string) $fields['service_municipality']);
        $code = $this->node($doc, $service, 'cServ');
        $this->node($doc, $code, 'cTribNac', (string) $fields['service_code']);
        $this->node($doc, $code, 'xDescServ', (string) $fields['service_description']);
        if (is_string($nbs)) {
            $this->node($doc, $code, 'cNBS', $nbs);
        }
        if ($foreign !== null) {
            $trade = $this->node($doc, $service, 'comExt');
            foreach ($foreign['trade_fields'] as $field => $value) {
                $this->node($doc, $trade, substr($field, strlen('serv.comExt.')), (string) $value);
            }
        }
        $values = $this->node($doc, $info, 'valores');
        $this->node($doc, $this->node($doc, $values, 'vServPrest'), 'vServ', (string) $fields['service_value']);
        $tax = $this->node($doc, $values, 'trib');
        $municipal = $this->node($doc, $tax, 'tribMun');
        $this->node($doc, $municipal, 'tribISSQN', $immuneIss ? '2' : '1');
        if ($immuneIss) {
            $this->node($doc, $municipal, 'tpImunidade', (string) $projection['iss']['tpImunidade']);
        }
        $this->node($doc, $municipal, 'tpRetISSQN', (string) $fields['iss_withholding']);
        if ($federal['fields'] !== []) {
            $federalNode = $this->node($doc, $tax, 'tribFed');
            $pisCofins = $this->node($doc, $federalNode, 'piscofins');
            $this->node($doc, $pisCofins, 'CST', $federal['fields']['piscofins.CST']);
            $this->node($doc, $pisCofins, 'tpRetPisCofins', $federal['fields']['piscofins.tpRetPisCofins']);
            foreach (['vRetCP', 'vRetIRRF', 'vRetCSLL'] as $field) {
                if (isset($federal['fields'][$field])) {
                    $this->node($doc, $federalNode, $field, $federal['fields'][$field]);
                }
            }
        }
        $this->node($doc, $this->node($doc, $tax, 'totTrib'), 'indTotTrib', (string) $identity['total_tax_indicator']);
        $rtc = $this->node($doc, $info, 'IBSCBS');
        $this->node($doc, $rtc, 'finNFSe', (string) $fields['rtc_purpose']);
        $this->node($doc, $rtc, 'cIndOp', (string) $fields['rtc_operation']);
        $this->node($doc, $rtc, 'indDest', (string) $fields['rtc_destination']);
        if ($fields['rtc_destination'] === '1' && is_string($recipient)) {
            $recipientNode = $this->node($doc, $rtc, 'dest');
            $this->node($doc, $recipientNode, strlen($recipient) === 11 ? 'CPF' : 'CNPJ', $recipient);
            $this->node($doc, $recipientNode, 'xNome', $identity['recipient_name']);
        }
        $group = $this->node($doc, $this->node($doc, $this->node($doc, $rtc, 'valores'), 'trib'), 'gIBSCBS');
        $this->node($doc, $group, 'CST', (string) $fields['rtc_cst']);
        $this->node($doc, $group, 'cClassTrib', (string) $fields['rtc_class']);

        $result = $this->result('PROJECTED', 'RTC_DPS_XML_CANDIDATE_BUILT', [], $doc->saveXML() ?: null);
        if ($foreign !== null) {
            $result['foreign_acquirer_binding'] = array_diff_key($foreign, ['party_xml' => true, 'trade_fields' => true]);
        }

        return $result;
    }

    private function node(DOMDocument $doc, DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $element = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', $name);
        if ($value !== null) {
            $element->appendChild($doc->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }

    private function dateMatches(string $value, string $format): bool
    {
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value;
    }

    /** @param list<string> $missing
     * @return array{status: string, reason: string, missing_fields: list<string>, xml: ?string}
     */
    private function result(string $status, string $reason, array $missing = [], ?string $xml = null): array
    {
        return ['status' => $status, 'reason' => $reason, 'missing_fields' => $missing, 'xml' => $xml];
    }
}
