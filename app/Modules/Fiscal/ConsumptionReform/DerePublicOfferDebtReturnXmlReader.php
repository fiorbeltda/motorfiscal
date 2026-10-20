<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use DOMDocument;
use DOMElement;
use DOMXPath;

/** Local synthetic reconciliation of immutable return and origin bytes; no reception authentication. */
final readonly class DerePublicOfferDebtReturnXmlReader
{
    /** @param array<string, mixed> $scope
     * @return array<string, mixed>
     */
    public function read(string $returnXml, string $originXml, array $scope, string $sources): array
    {
        $identity = ['return_xml_sha256' => hash('sha256', $returnXml), 'origin_xml_sha256' => hash('sha256', $originXml),
            'received_return_verified' => false, 'eligible_for_posting' => false, 'production_ready' => false];
        if (($scope['synthetic'] ?? null) !== true || ! is_string($scope['supplier_id'] ?? null)
            || ! preg_match('/^[0-9]{14}$/', $scope['supplier_id']) || ! is_string($scope['period'] ?? null)
            || ! preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $scope['period'])
            || ($scope['origin_xml_sha256'] ?? null) !== $identity['origin_xml_sha256']
            || ! is_string($scope['expected_sequence'] ?? null) || ! preg_match('/^[0-9]{2}$/', $scope['expected_sequence'])
            || ! is_string($scope['pgcc_receipt'] ?? null)) {
            return $this->stop($identity, 'EXPLICIT_SYNTHETIC_BOUND_SCOPE_REQUIRED');
        }
        $schemas = [];
        foreach (['return' => [$returnXml, 'evtRetornoTitPub-v0_0_2.xsd'], 'origin' => [$originXml, 'evtDebOpOfPublic-v0_0_3.xsd']] as $kind => [$xml, $schema]) {
            $schemas[$kind] = (new DereXmlSchemaValidator)->validate($xml,
                $sources.'/dere-observed-20261006-cgibs-xsd-v1-3-0.zip',
                '3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd', $schema,
                $sources.'/w3c-xmldsig-core-schema-observed-20261002.xsd',
                'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
            if ($schemas[$kind]['reason'] !== 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE') {
                return $this->stop([...$identity, 'schema_validations' => $schemas], 'SCHEMA_OR_UNSIGNED_SYNTHETIC_SCOPE_UNPROVEN');
            }
        }
        $identity['schema_validations'] = $schemas;
        $return = $this->xpath($returnXml, 'http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2');
        $origin = $this->xpath($originXml, 'http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3');
        $r = '/d:DeRE/d:evtRetornoTitPub';
        $o = '/d:DeRE/d:evtDebOpOfPublic';
        $root = substr($scope['supplier_id'], 0, 8);
        $expectedHash = base64_encode(hash('sha256', $originXml, true));
        if ($origin->evaluate('string('.$o.'/d:ideEvento/d:tpAmb)') !== '2'
            || ! in_array($origin->evaluate('string('.$o.'/d:ideEvento/d:tpOper)'), ['1', '2'], true)) {
            return $this->stop($identity, 'SANDBOX_NON_EXCLUSION_ORIGIN_REQUIRED');
        }
        if ($return->evaluate('string('.$r.'/@id)') !== $origin->evaluate('string('.$o.'/@id)')
            || $return->evaluate('string('.$r.'/d:ideContrib/d:nrInsc)') !== $root
            || $origin->evaluate('string('.$o.'/d:ideContrib/d:nrInsc)') !== $root
            || $return->evaluate('string('.$r.'/d:infoEvento/d:idePeriodo/d:perApur)') !== $scope['period']
            || $origin->evaluate('string('.$o.'/d:idePeriodo/d:perApur)') !== $scope['period']
            || $return->evaluate('string('.$r.'/d:ideStatus/d:cdRetorno)') !== '1'
            || $return->evaluate('string('.$r.'/d:ideStatus/d:descRetorno)') !== 'Sucesso'
            || $return->evaluate('string('.$r.'/d:infoRecEv/d:tpEv)') !== 'D-2101'
            || $return->evaluate('string('.$r.'/d:infoRecEv/d:seqEvento)') !== $scope['expected_sequence']
            || $return->evaluate('string('.$r.'/d:infoRecEv/d:hash)') !== $expectedHash
            || $return->evaluate('string('.$r.'/d:infoRecEv/d:nrRecibo)') === ''
            || $return->evaluate('string('.$r.'/d:infoEvento/d:infoAdic/d:nrReciboPGCC)') !== $scope['pgcc_receipt']) {
            return $this->stop($identity, 'RETURN_OR_ORIGIN_CONTEXT_CONFLICT');
        }
        $totals = $return->query($r.'/d:infoEvento/d:infoTotTitOfPub/d:vApurTot');
        $titles = $origin->query($o.'//d:detTitulo');
        if ($totals === false || $totals->length !== 1 || $titles === false) {
            return $this->stop($identity, 'TOTALIZATION_REQUIRED');
        }
        $sum = DecimalValue::fromString('0.00');
        foreach ($titles as $title) {
            if (! $title instanceof DOMElement) {
                return $this->stop($identity, 'TITLE_STRUCTURE_CONFLICT');
            }
            $value = $origin->evaluate('string(d:vApur)', $title);
            if (! is_string($value)) {
                return $this->stop($identity, 'TITLE_STRUCTURE_CONFLICT');
            }
            $sum = $sum->plus(DecimalValue::fromString($value));
        }
        if ($titles->length === 0 && $origin->evaluate('string('.$o.'/d:infoDebOpOfPublic/d:semTitulos)') !== '1') {
            return $this->stop($identity, 'TITLE_COVERAGE_REQUIRED');
        }
        $total = $return->evaluate('string('.$r.'/d:infoEvento/d:infoTotTitOfPub/d:vApurTot)');
        if (! is_string($total) || $sum->compareTo(DecimalValue::fromString($total)) !== 0) {
            return $this->stop($identity, 'ORIGIN_AGGREGATE_AMOUNT_CONFLICT');
        }

        return [...$identity, 'status' => 'LOCAL_D9121_AGGREGATE_RECONCILED', 'supplier_id' => $scope['supplier_id'],
            'period' => $scope['period'], 'title_count' => $titles->length, 'total_vApur' => $total,
            'origin_event_id' => $origin->evaluate('string('.$o.'/@id)'), 'expected_sequence' => $scope['expected_sequence'],
            'pgcc_receipt' => $scope['pgcc_receipt'], 'hash_encoding_scope' => 'SYNTHETIC_BASE64_SHA256_RAW_ORIGIN_BYTES',
            'active_origin_version_qualified' => false, 'composition_integrated' => false];
    }

    private function xpath(string $xml, string $namespace): DOMXPath
    {
        $document = new DOMDocument;
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $document->loadXML($xml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('d', $namespace);

        return $xpath;
    }

    /** @param array<string, mixed> $identity
     * @return array<string, mixed>
     */
    private function stop(array $identity, string $reason): array
    {
        return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_D9121_'.$reason];
    }
}
