<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Throwable;

/** Local sandbox composition with source-qualified own contributions and bound payment replays. */
final readonly class PartialExportFederalPair137
{
    public const AVAILABLE_AT = '2026-10-23T18:02:44+00:00';

    /**
     * @param  array<array-key, mixed>  $snapshot
     * @param  array<array-key, mixed>  $declarations
     * @param  array<array-key, mixed>  $payments
     * @param  array<array-key, mixed>  $ownSelections
     * @return array<string, mixed>
     */
    public function project(array $snapshot, array $declarations, array $payments, array $ownSelections, string $root): array
    {
        try {
            $known = new DateTimeImmutable($snapshot['input']['knowledge_at']);
            $this->require($known >= new DateTimeImmutable(self::AVAILABLE_AT), 'POLICY_NOT_YET_KNOWN');
            $payment = (new DocumentFederalPayment137)->bind($snapshot, $declarations, $payments, DocumentFederalPayment137::policy(), $root);
            $this->require($payment['status'] === 'STAGED_PER_DOCUMENT_FEDERAL_PAYMENTS_BOUND', 'PAYMENT_BINDING_REQUIRED:'.($payment['reason'] ?? ''));
            $pair = (new PartialExportDpsPair135)->project($snapshot, $declarations, $root);
            $rtc = array_values(array_filter($snapshot['result']['trace'], static fn (array $t): bool => $t['type'] === 'CONSUMPTION_REFORM'))[0]['payload'];
            $iss = array_values(array_filter($rtc['trace'], static fn (array $t): bool => $t['stage'] === 'PARTIAL_EXPORT_DOCUMENT_ISS134'))[0];
            $own = (new OwnContributionFields137)->qualify($iss, $declarations['own_tax_proofs'], $ownSelections,
                OwnContributionFields137::policy(), $known, $root.'/sources/official/own136-sped', $root);
            $this->require($own['status'] === 'STAGED_OWN_CONTRIBUTION_FIELDS_QUALIFIED', 'OWN_SELECTION_REQUIRED:'.($own['reason'] ?? ''));
            $this->require($own['own_result_hash'] === CanonicalJson::hash($pair['bridge']['fresh_own_calculation']), 'OWN_PRICE_BRIDGE_CONFLICT');
            $ids = [];
            foreach ([...$declarations['own_tax_proofs'], ...$declarations['comex_proofs'], $declarations['documentary_proof'], ...$payments, ...$ownSelections] as $proof) {
                $this->require(! isset($ids[$proof['evidence_id']]), 'DISTINCT_EVIDENCE_IDS_REQUIRED');
                $ids[$proof['evidence_id']] = true;
            }
            foreach ($ownSelections as $selection) {
                foreach ([...$payments, $declarations['documentary_proof']] as $dependency) {
                    $this->require(new DateTimeImmutable($selection['observed_at']) >= new DateTimeImmutable($dependency['observed_at']), 'OWN_SELECTION_PREDATES_DOCUMENT_OR_PAYMENT');
                }
            }
            $ownRows = array_column($own['documents'], null, 'role');
            $paymentRows = array_column($payment['documents'], null, 'role');
            $schema = $snapshot['knowledge']['documents']['rtc_code_catalog']['dps_xsd'];
            $records = [];
            foreach ($pair['records'] as $record) {
                $ownRow = $ownRows[$record['role']];
                $paymentRow = $paymentRows[$record['role']];
                $this->require($record['document_reference'] === $ownRow['document_reference']
                    && $record['document_reference'] === $paymentRow['document_reference'], 'BOUND_DOCUMENT_REFERENCES_REQUIRED');
                $fields = $ownRow['piscofins'];
                foreach (['PIS' => 'vPis', 'COFINS' => 'vCofins'] as $tax => $field) {
                    $this->require($fields[$field] === $paymentRow['own_incident_amounts_brl'][$tax], 'OWN_DEBIT_CONFLICT_WITH_BOUND_REPLAY');
                    $check = (new OwnDebitTolerance137)->compare($fields['vBCPisCofins'] ?? null,
                        $fields[$tax === 'PIS' ? 'pAliqPis' : 'pAliqCofins'] ?? null, $fields[$field],
                        $fields['CST'] === '08' ? 'NON_INCIDENCE' : 'TAXABLE');
                    $this->require($check['status'] !== 'ACTION_REQUIRED', 'OWN_DEBIT_TOLERANCE_REQUIRED');
                }
                $retained = $paymentRow['retention_fields'];
                foreach (['vRetCP', 'vRetIRRF'] as $name) {
                    if (isset($retained[$name])) {
                        $this->require(BigDecimal::of($retained[$name])->compareTo('0') > 0
                            && BigDecimal::of($retained[$name])->compareTo($paymentRow['gross_document_price_brl']) < 0, 'RETAINED_FIELD_OUTSIDE_LAYOUT_LIMITS');
                    }
                }
                $fields['tpRetPisCofins'] = $retained['piscofins.tpRetPisCofins'];
                $this->require(($fields['tpRetPisCofins'] === '0') === ! isset($retained['vRetCSLL']), 'RETENTION_INDICATOR_AND_SOCIAL_TOTAL_CONFLICT');
                $doc = new DOMDocument;
                $this->require($doc->loadXML($record['xml'], LIBXML_NONET), 'PRIOR_XML_REQUIRED');
                $xpath = new DOMXPath($doc);
                $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');
                $tribs = $xpath->query('/n:DPS/n:infDPS/n:valores/n:trib');
                $trib = $tribs === false ? null : $tribs->item(0);
                if (! $trib instanceof DOMElement) {
                    throw new RuntimeException('TAX_ELEMENT_REQUIRED');
                }
                $totals = $xpath->query('n:totTrib', $trib);
                $existing = $xpath->query('n:tribFed', $trib);
                $tot = $totals === false ? null : $totals->item(0);
                if (! $tot instanceof DOMElement || $totals->length !== 1 || $existing === false || $existing->length !== 0) {
                    throw new RuntimeException('EXACT_PRIOR_TAX_SEQUENCE_REQUIRED');
                }
                $federal = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'tribFed');
                $pis = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'piscofins');
                foreach ($fields as $name => $value) {
                    $node = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', $name);
                    $node->appendChild($doc->createTextNode($value));
                    $pis->appendChild($node);
                }
                $federal->appendChild($pis);
                foreach (['vRetCP', 'vRetIRRF', 'vRetCSLL'] as $name) {
                    if (isset($retained[$name])) {
                        $node = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', $name);
                        $node->appendChild($doc->createTextNode($retained[$name]));
                        $federal->appendChild($node);
                    }
                }
                $trib->insertBefore($federal, $tot);
                $xml = (string) $doc->saveXML();
                $validation = (new DpsXmlSchemaValidator)->validate($xml, $root.'/sources/official/'.$schema['source_file'], $schema['source_sha256'], $schema['root_schema']);
                $this->require($validation['status'] === 'VALID', 'FULL_XSD_REQUIRED:'.json_encode($validation['errors']));
                $records[] = [...$record, 'xml' => $xml, 'xml_sha256' => hash('sha256', $xml), 'validation' => $validation,
                    'own_fields' => $fields, 'retention_fields' => $retained, 'bank_net_brl' => $paymentRow['bank_net_brl'],
                    'withholding_payment_proof_hash' => $paymentRow['proof_hash'], 'own_selection_proof_hash' => $ownRow['declaration_hash']];
            }

            return ['status' => 'LOCAL_OWN_AND_WITHHELD_PAIR_XML_XSD_VALIDATED', 'records' => $records,
                'own_fields' => $own, 'payment_binding' => $payment, 'prior135_projection_hash' => CanonicalJson::hash($pair),
                'own_pis_cofins_replaced_with_withholding' => false, 'nt007_original_bytes_captured' => true,
                'complete_dps_built' => true, 'installed_in_application' => true, 'document_obligation_complete' => false,
                'complete_fiscal_pair_qualified' => false, 'production_ready' => false];
        } catch (Throwable $e) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => 'PAIR137_'.$e->getMessage(),
                'records' => [], 'eligible_for_integration' => false, 'production_ready' => false];
        }
    }

    private function require(bool $condition, string $reason): void
    {
        if (! $condition) {
            throw new RuntimeException($reason);
        }
    }
}
