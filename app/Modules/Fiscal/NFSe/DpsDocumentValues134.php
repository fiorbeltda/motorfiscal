<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DOMDocument;
use DOMElement;

/** Project official DPS input fields from independently calculated document ISS values. */
final readonly class DpsDocumentValues134
{
    /** @param array<string, mixed> $assessment
     * @param  array<int|string, mixed>  $options
     * @return array<string, mixed>
     */
    public function project(array $assessment, array $options): array
    {
        if (($assessment['status'] ?? null) !== 'LOCAL_ISS_DOCUMENT_BASES_CALCULATED'
            || count($assessment['documents'] ?? []) !== 2 || ! array_is_list($options) || count($options) !== 2) {
            return $this->stop('TWO_CURRENT_DOCUMENTS_REQUIRED');
        }
        $byRole = [];
        foreach ($options as $option) {
            $keys = ['role', 'document_reference', 'withholding', 'rate_source', 'result_country', 'total_tax_indicator', 'evidence_id'];
            if (! is_array($option) || count($option) !== count($keys) || array_diff(array_keys($option), $keys) !== []) {
                return $this->stop('EXACT_DOCUMENTARY_OPTIONS_REQUIRED');
            }
            foreach (array_diff($keys, ['result_country']) as $key) {
                if (! is_string($option[$key]) || trim($option[$key]) === '') {
                    return $this->stop('EXPLICIT_DOCUMENTARY_OPTIONS_REQUIRED');
                }
            }
            if (! in_array($option['role'], ['RTC_FOREIGN', 'RTC_DOMESTIC'], true) || isset($byRole[$option['role']])) {
                return $this->stop('DISTINCT_DOCUMENTARY_OPTIONS_REQUIRED');
            }
            $byRole[$option['role']] = $option;
        }
        $fragments = [];
        foreach ($assessment['documents'] as $values) {
            if (! is_array($values) || ! is_string($values['role'] ?? null) || ! isset($byRole[$values['role']])) {
                return $this->stop('TWO_CURRENT_DOCUMENTS_REQUIRED');
            }
            $option = $byRole[$values['role']];
            $export = $values['iss_treatment'] === 'LOCAL_EXPORT_NON_INCIDENCE_CANDIDATE';
            if ($option['document_reference'] !== $values['document_reference']
                || ! in_array($option['withholding'], ['1', '2', '3'], true) || $option['total_tax_indicator'] !== '0'
                || ($export && ($option['rate_source'] !== 'NOT_APPLICABLE' || $option['withholding'] !== '1'
                    || ! is_string($option['result_country']) || preg_match('/\A[A-Z]{2}\z/', $option['result_country']) !== 1 || $option['result_country'] === 'BR'))
                || (! $export && (! in_array($option['rate_source'], ['NATIONAL_SYSTEM_PARAMETERIZED', 'ISSUER_SUPPLIED'], true)
                    || $option['result_country'] !== null))) {
                return $this->stop('DOCUMENTARY_OPTION_CONTEXT_CONFLICT');
            }
            if ($values['price_currency'] !== 'BRL') {
                return $this->stop('OWN_DOCUMENT_CURRENCY_REQUIRED');
            }
            $doc = new DOMDocument('1.0', 'UTF-8');
            $doc->formatOutput = true;
            $root = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'valores');
            $doc->appendChild($root);
            $this->node($doc, $this->node($doc, $root, 'vServPrest'), 'vServ', $values['gross_service_price']);
            $discounts = $this->node($doc, $root, 'vDescCondIncond');
            $this->node($doc, $discounts, 'vDescIncond', $values['unconditional_discount']);
            $this->node($doc, $discounts, 'vDescCond', $values['conditional_discount']);
            $tax = $this->node($doc, $root, 'trib');
            $municipal = $this->node($doc, $tax, 'tribMun');
            $this->node($doc, $municipal, 'tribISSQN', $export ? '3' : '1');
            if ($export) {
                $this->node($doc, $municipal, 'cPaisResult', $option['result_country']);
            }
            $this->node($doc, $municipal, 'tpRetISSQN', $option['withholding']);
            if ($option['rate_source'] === 'ISSUER_SUPPLIED') {
                $this->node($doc, $municipal, 'pAliq', $values['iss_rate_percent']);
            }
            $this->node($doc, $this->node($doc, $tax, 'totTrib'), 'indTotTrib', '0');
            $fragments[] = ['role' => $values['role'], 'document_reference' => $values['document_reference'],
                'xml' => $doc->saveXML(), 'iss_base_brl' => $values['iss_base_brl'], 'iss_tax_brl' => $values['iss_tax_brl'],
                'municipal_service_code' => $values['municipal_code'], 'documentary_options' => $option];
        }

        return ['status' => 'LOCAL_DOCUMENT_VALUES_PROJECTED', 'fragments' => $fragments,
            'assessment_binding_hash' => $assessment['binding_hash'], 'options_sha256' => CanonicalJson::hash($options),
            'omitted_fields' => ['cTribMun' => 'No proven three-digit mapping from the five-digit municipal service code.',
                'vBC_and_vISSQN' => 'Calculated amounts retained in report;not fields of the DPS input type.'],
            'complete_dps_built' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }

    private function node(DOMDocument $doc, DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $node = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', $name);
        if ($value !== null) {
            $node->appendChild($doc->createTextNode($value));
        }
        $parent->appendChild($node);

        return $node;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DPS134_'.$reason,
            'complete_dps_built' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }
}
