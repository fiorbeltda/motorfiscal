<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/** Adds own contribution fields to the unchanged135 pair after its full replay. */
final readonly class PartialExportDpsPair136
{
    public const CONTRACT = 'LOCAL-PARTIAL-EXPORT-DPS-PAIR-136-V1';

    public const AVAILABLE_AT = '2026-10-23T16:19:32+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT,
            'prior_projection' => PartialExportDpsPair135::policy(), 'own_federal' => OwnFederalValues136::policy(),
            'sequence' => 'TRIB_MUN_THEN_TRIB_FED_THEN_TOT_TRIB',
            'own_federal_xml_scope' => 'OWN_PIS_COFINS_WITH_EXPLICIT_NOT_WITHHELD_DECLARATIONS',
            'scope' => 'LOCAL_UNSIGNED_SANDBOX_PAIR_NOT_FULFILLMENT'];
    }

    /** @param array<string, mixed> $snapshot
     * @param  array<string, mixed>  $declarations
     * @return array<string, mixed>
     */
    public function project(array $snapshot, array $declarations, string $resourceRoot): array
    {
        $keys = ['own_tax_proofs', 'comex_proofs', 'documentary_proof', 'own_federal_proofs'];
        $this->require(count($declarations) === count($keys) && array_diff(array_keys($declarations), $keys) === []
            && is_array($declarations['own_federal_proofs']), 'Exact136 declaration groups required.');
        $prior = array_diff_key($declarations, ['own_federal_proofs' => true]);
        $pair = (new PartialExportDpsPair135)->project($snapshot, $prior, $resourceRoot);
        $known = new DateTimeImmutable($snapshot['input']['knowledge_at']);
        $this->require($known >= new DateTimeImmutable(self::AVAILABLE_AT), 'Projection136 not yet available at this knowledge instant.');
        $ids = [];
        foreach ([...$declarations['own_tax_proofs'], ...$declarations['comex_proofs'], $declarations['documentary_proof'], ...$declarations['own_federal_proofs']] as $proof) {
            $id = is_array($proof) ? ($proof['evidence_id'] ?? null) : null;
            $this->require(is_string($id) && trim($id) !== '' && ! isset($ids[$id]), 'Distinct own,COMEX,documentary and federal evidence identifiers required.');
            $ids[$id] = true;
        }
        $rtcItems = array_values(array_filter($snapshot['result']['trace'], static fn (array $t): bool => $t['type'] === 'CONSUMPTION_REFORM'));
        $this->require(count($rtcItems) === 1, 'Unique replayed RTC required.');
        $issItems = array_values(array_filter($rtcItems[0]['payload']['trace'], static fn (array $t): bool => $t['stage'] === 'PARTIAL_EXPORT_DOCUMENT_ISS134'));
        $this->require(count($issItems) === 1, 'Unique replayed document ISS required.');
        $fields = (new OwnFederalValues136)->qualify($issItems[0], $declarations['own_tax_proofs'], $declarations['own_federal_proofs'],
            OwnFederalValues136::policy(), $known, $resourceRoot.'/sources/official/own136-sped', $resourceRoot);
        $this->require($fields['status'] === 'LOCAL_OWN_FEDERAL_FIELDS_QUALIFIED', 'Own federal136 refused:'.($fields['reason'] ?? 'unknown'));
        $this->require($fields['own_result_hash'] === CanonicalJson::hash($pair['bridge']['fresh_own_calculation']), 'Fresh own federal calculation differs from price bridge.');
        $byRole = array_column($fields['documents'], null, 'role');
        $schema = $snapshot['knowledge']['documents']['rtc_code_catalog']['dps_xsd'];
        $records = [];
        foreach ($pair['records'] as $record) {
            $row = $byRole[$record['role']] ?? null;
            $this->require(is_array($row) && $row['document_reference'] === $record['document_reference'], 'Own federal document reference conflict.');
            $doc = new DOMDocument;
            $this->require($doc->loadXML($record['xml'], LIBXML_NONET), 'Prior complete DPS XML invalid.');
            $xpath = new DOMXPath($doc);
            $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');
            $tribs = $xpath->query('/n:DPS/n:infDPS/n:valores/n:trib');
            if ($tribs === false || $tribs->length !== 1) {
                throw new RuntimeException('Unique document tax group required.');
            }
            $trib = $tribs->item(0);
            if (! $trib instanceof DOMElement) {
                throw new RuntimeException('Tax group element required.');
            }
            $totals = $xpath->query('n:totTrib', $trib);
            $existing = $xpath->query('n:tribFed', $trib);
            if ($totals === false || $totals->length !== 1 || $existing === false || $existing->length !== 0) {
                throw new RuntimeException('Prior tax sequence or federal group conflict.');
            }
            $total = $totals->item(0);
            if (! $total instanceof DOMElement) {
                throw new RuntimeException('Total tax element required.');
            }
            $federal = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'tribFed');
            $group = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'piscofins');
            foreach ($row['piscofins'] as $name => $value) {
                $node = $doc->createElementNS('http://www.sped.fazenda.gov.br/nfse', $name);
                $node->appendChild($doc->createTextNode($value));
                $group->appendChild($node);
            }
            $federal->appendChild($group);
            $trib->insertBefore($federal, $total);
            $xml = (string) $doc->saveXML();
            $validation = (new DpsXmlSchemaValidator)->validate($xml, $resourceRoot.'/sources/official/'.$schema['source_file'], $schema['source_sha256'], $schema['root_schema']);
            $this->require($validation['status'] === 'VALID', 'Complete own136 DPS XSD refused:'.json_encode($validation['errors']));
            $records[] = [...$record, 'xml' => $xml, 'xml_sha256' => hash('sha256', $xml), 'validation' => $validation,
                'own_federal_fields' => $row['piscofins'], 'own_federal_declaration_hash' => $row['declaration_hash']];
        }

        return [...$pair, 'status' => 'LOCAL_PARTIAL_EXPORT_OWN_FEDERAL_PAIR_DPS_XSD_VALIDATED', 'contract' => self::CONTRACT,
            'records' => $records, 'own_federal' => $fields, 'projection_policy_hash' => CanonicalJson::hash(self::policy()),
            'prior135_additional_sources_valid' => $pair['additional_sources_valid'],
            'own_federal_sources_valid' => count(OwnFederalValues136::policy()['table_sources']),
            'additional_sources_valid' => $pair['additional_sources_valid'] + count(OwnFederalValues136::policy()['table_sources']),
            'prior135_projection_hash' => CanonicalJson::hash($pair), 'own_pis_cofins_projected_in_xml' => true,
            'complete_fiscal_pair_qualified' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}
