<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\DereDeductionQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;

/** Original local D1121 candidate; external document veracity remains a qualified input. */
final readonly class DereDeductionXmlProjector
{
    public const CONTRACT = 'DERE-DEDUCTIONS-LOCAL-2026.10.02-1';

    /** @param array<string, mixed> $operation
     * @param  array<string, mixed>  $proof
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>|null  $accountBinding
     * @return array<string, mixed>
     */
    public function project(array $operation, array $proof, array $identity, DateTimeImmutable $knownAt, array $rule, ?array $accountBinding = null): array
    {
        $schemaVersion = $rule['schema_version'] ?? 'v0_0_1';
        if (! in_array($schemaVersion, ['v0_0_1', 'v0_0_2'], true)) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_DEDUCTIONS_SCHEMA_VERSION_UNSUPPORTED', 'production_ready' => false];
        }
        $deductions = (new DereDeductionQualifier)->resolve($operation, $proof, $knownAt, $rule);
        if ($deductions['status'] !== 'RESOLVED') {
            return $deductions;
        }
        if ($schemaVersion === 'v0_0_2') {
            if (($accountBinding['status'] ?? null) !== 'RESOLVED'
                || ($accountBinding['reason'] ?? null) !== 'RTC_DERE_D1121_PGCC_BINDING_QUALIFIED'
                || ($accountBinding['scoped_entries_hash'] ?? null) !== CanonicalJson::hash($operation['base']['entries'])) {
                return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_DEDUCTIONS_1_3_QUALIFIED_BINDING_REQUIRED', 'production_ready' => false];
            }
            if (count($proof['documents']) > 20000) {
                return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_DEDUCTIONS_1_3_DOCUMENT_LIMIT', 'production_ready' => false];
            }
            // This contract covers the already reconciled single-account art192 correspondent.
            foreach ($proof['documents'] as $doc) {
                if (! is_array($doc['pgcc_account_codes'] ?? null) || count($doc['pgcc_account_codes']) !== 1
                    || ! is_string($doc['pgcc_account_codes'][0] ?? null)
                    || preg_match('/^[0-9A-Za-z]{1,53}$/', $doc['pgcc_account_codes'][0]) !== 1
                    || ($doc['account_binding_evidence']['qualified'] ?? null) !== true) {
                    return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_DEDUCTIONS_1_3_ACCOUNT_BINDING_REQUIRED', 'production_ready' => false];
                }
                if (isset($doc['previous_deduction']) && (($doc['previous_deduction']['pgcc_account_code'] ?? null) !== $doc['pgcc_account_codes'][0]
                    || ($doc['previous_deduction']['source_hash'] ?? null) !== CanonicalJson::hash(array_diff_key($doc['previous_deduction'], ['source_hash' => true])))) {
                    return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_DEDUCTIONS_1_3_PREVIOUS_ACCOUNT_UNPROVEN', 'production_ready' => false];
                }
            }
        }
        $supplier = $proof['supplier_id'];
        $sequence = $identity['event_sequence'] ?? null;
        $generated = $identity['generated_at'] ?? null;
        if (! is_string($supplier) || preg_match('/^[0-9A-Z]{14}$/', $supplier) !== 1
            || ! is_int($sequence) || $sequence < 1 || $sequence > 99999
            || ! is_string($generated) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/', $generated) !== 1) {
            return ['status' => 'INVALID', 'reason' => 'RTC_DERE_DEDUCTIONS_EVENT_IDENTITY_INVALID'];
        }
        $time = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.vP', $generated);
        if ($time === false || $time->format('Y-m-d\TH:i:s.vP') !== $generated || $time > $knownAt
            || substr($generated, 0, 10) <= $proof['period_end']) {
            return ['status' => 'INVALID', 'reason' => 'RTC_DERE_DEDUCTIONS_GENERATION_DATE_CONFLICT'];
        }
        $xml = new DOMDocument('1.0', 'UTF-8');
        $root = $xml->createElementNS('http://www.dere.gov.br/schemas/evtRelDeducoes/'.$schemaVersion, 'DeRE');
        $xml->appendChild($root);
        $event = $this->node($xml, $root, 'evtRelDeducoes');
        $event->setAttribute('id', 'DeRE11211'.$supplier.$time->format('YmdHis').str_pad((string) $sequence, 5, '0', STR_PAD_LEFT));
        $header = $this->node($xml, $event, 'ideEvento');
        foreach (['tpOper' => '1', 'tpAmb' => '2', 'aplicEmi' => '1', 'verAplic' => 'motor-local-1'] as $name => $value) {
            $this->node($xml, $header, $name, $value);
        }
        $this->node($xml, $this->node($xml, $event, 'ideContrib'), 'nrInsc', substr($supplier, 0, 8));
        $this->node($xml, $this->node($xml, $event, 'idePeriodo'), 'perApur', $deductions['period']);
        $info = $this->node($xml, $event, 'infoDeducoes');
        foreach ($deductions['documents'] as $index => $doc) {
            $row = $this->node($xml, $info, 'infoDeducao');
            $dfe = $this->node($xml, $row, 'infoDFe');
            if ($schemaVersion === 'v0_0_2') {
                $dfe->setAttribute('chDFe', $doc['chDFe']);
            }
            foreach ($schemaVersion === 'v0_0_2' ? ['tpDFe', 'dtEmi'] : ['tpDFe', 'chDFe', 'dtEmi', 'tpAtiv'] as $name) {
                $this->node($xml, $dfe, $name, $doc[$name]);
            }
            $detail = $this->node($xml, $row, 'detDeducao');
            if ($schemaVersion === 'v0_0_2') {
                $this->node($xml, $detail, 'vOper', $doc['vOper']);
                $detail = $this->node($xml, $detail, 'desdobDeducao');
                $detail->setAttribute('tpAtiv', $doc['tpAtiv']);
                $this->node($xml, $detail, 'cCta', $proof['documents'][$index]['pgcc_account_codes'][0]);
            }
            foreach (['vOper', 'vDedTotal', 'vDed'] as $name) {
                if (isset($doc[$name]) && ! ($schemaVersion === 'v0_0_2' && $name === 'vOper')) {
                    $this->node($xml, $detail, $name, $doc[$name]);
                }
            }
            foreach ($doc['items'] ?? [] as $item) {
                $itemNode = $this->node($xml, $schemaVersion === 'v0_0_2' ? $detail : $row, 'itemDFe');
                if ($schemaVersion === 'v0_0_2') {
                    $itemNode->setAttribute('nItem', (string) $item['nItem']);
                }
                foreach (['nItem', 'vItem', 'vItemDedTotal', 'vItemDed'] as $name) {
                    if (isset($item[$name]) && ! ($schemaVersion === 'v0_0_2' && $name === 'nItem')) {
                        $this->node($xml, $itemNode, $name, $item[$name]);
                    }
                }
            }
        }

        return ['status' => 'PROJECTED', 'reason' => 'RTC_DERE_DEDUCTIONS_LOCAL_PROJECTED', 'contract' => self::CONTRACT,
            'xml' => $xml->saveXML(), 'deductions' => $deductions, 'schema' => 'evtRelDeducoes-'.$schemaVersion.'.xsd',
            'production_ready' => false, 'signature_created' => false, 'transmission_performed' => false];
    }

    private function node(DOMDocument $xml, DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $element = $xml->createElementNS($parent->namespaceURI, $name);
        if ($value !== null) {
            $element->appendChild($xml->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }
}
