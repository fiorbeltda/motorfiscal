<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use DOMDocument;
use DOMElement;
use DOMXPath;

/** Reads immutable return bytes; schema and reconciliation never establish government reception. */
final readonly class DereNegativeBaseReturnXmlReader
{
    public const SCHEMA_HASH = '3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd';

    /** @param array<string, mixed> $scope
     * @return array<string, mixed>
     */
    public function read(string $xml, array $scope, string $officialSourceDirectory, bool $syntheticUnsigned = false): array
    {
        $schema = (new DereXmlSchemaValidator)->validate($xml,
            $officialSourceDirectory.'/dere-observed-20261006-cgibs-xsd-v1-3-0.zip', self::SCHEMA_HASH,
            'evtRetornoMensal-v0_0_2.xsd', $officialSourceDirectory.'/w3c-xmldsig-core-schema-observed-20261002.xsd',
            'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
        $identity = ['xml_sha256' => hash('sha256', $xml), 'schema' => $schema,
            'synthetic_unsigned' => $syntheticUnsigned, 'received_return_verified' => false,
            'eligible_for_consumption' => false, 'production_ready' => false];
        if ($schema['status'] !== 'VALID' && ! ($syntheticUnsigned
            && $schema['reason'] === 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE')) {
            return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_XML_SCHEMA_UNPROVEN'];
        }
        $document = new DOMDocument;
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_XML_MALFORMED'];
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('d', 'http://www.dere.gov.br/schemas/evtRetornoMensal/v0_0_2');
        $event = '/d:DeRE/d:evtRetornoMensal';
        $value = static fn (string $path): string => $xpath->evaluate('string('.$path.')');
        $registrationRoot = $value($event.'/d:ideContrib/d:nrInsc');
        $supplier = $scope['supplier_id'] ?? null;
        $period = $value($event.'/d:infoEvento/d:idePeriodo/d:perApur');
        $sequence = $value($event.'/d:infoRecEv/d:seqEvento');
        if (! is_string($supplier) || ! preg_match('/^[0-9]{14}$/', $supplier)
            || $registrationRoot !== substr($supplier, 0, 8) || $period !== ($scope['period'] ?? null)
            || $value($event.'/d:ideStatus/d:cdRetorno') !== '1'
            || $value($event.'/d:infoRecEv/d:tpEv') !== 'D-1199'
            || ! preg_match('/^[0-9]{2}$/', $sequence)
            || ! is_string($scope['closing_event_hash'] ?? null)
            || ! preg_match('/^[0-9A-Za-z+\/]{43}=$/', $scope['closing_event_hash'])
            || $value($event.'/d:infoRecEv/d:hash') !== $scope['closing_event_hash']
            || $value($event.'/d:infoRecEv/d:nrRecibo') === '') {
            return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_XML_RETURN_SCOPE_OR_CLOSING_CONFLICT'];
        }
        $groups = [];
        $bases = $xpath->query($event.'/d:infoEvento/d:infoTotFinanceiro/d:detBC');
        if ($bases === false || $bases->length === 0) {
            return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_XML_FINANCIAL_TOTALIZATION_MISSING'];
        }
        foreach ($bases as $base) {
            if (! $base instanceof DOMElement) {
                continue;
            }
            // Node paths depend on document prefixes; use relative namespace-qualified XPath instead.
            $get = static fn (string $relative): string => $xpath->evaluate('string('.$relative.')', $base);
            foreach (['I' => ['IBS', 'vBCIBS', 'vBCNIBS'], 'C' => ['CBS', 'vBCCBS', 'vBCNCBS']] as $tax => [$name, $positiveTag, $negativeTag]) {
                $pools = $xpath->query('d:infoBCN/d:gBCN'.$name, $base);
                $pool = $pools === false ? null : $pools->item(0);
                if (! $pool instanceof DOMElement) {
                    return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_XML_STOCK_COVERAGE_MISSING'];
                }
                $poolValue = static fn (string $tag): string => $xpath->evaluate('string(d:'.$tag.')', $pool);
                $group = ['codBC' => $get('d:codBC'), 'codBCNRaiz' => $poolValue('codBCNRaiz'),
                    'positive_base' => $get('d:gBC'.$name.'/d:'.$positiveTag),
                    'generated_negative' => $get('d:gBC'.$name.'/d:'.$negativeTag) ?: '0.00',
                    'vSaldoAnt' => $poolValue('vSaldoAnt'), 'vUtilPer' => $poolValue('vUtilPer'),
                    'vSaldoFinal' => $poolValue('vSaldoFinal'), 'detBCN' => []];
                $declaredOrigins = $poolValue('qtdOrigens');
                $deduction = $get('d:gBC'.$name.'/d:vDedBCN') ?: '0.00';
                if ($deduction !== $group['vUtilPer'] || substr($group['codBCNRaiz'], -1) !== $tax) {
                    return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_XML_TAX_POOL_OR_DEDUCTION_CONFLICT'];
                }
                $assessed = $get('d:gBC'.$name.'/d:vBCApur'.$name);
                if (DecimalValue::fromString($assessed)->compareTo(DecimalValue::fromString($group['positive_base'])
                    ->minus(DecimalValue::fromString($deduction))) !== 0) {
                    return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_XML_ASSESSED_BASE_CONFLICT'];
                }
                $details = $xpath->query('d:detBCN', $pool);
                if ($details !== false) {
                    foreach ($details as $detail) {
                        if (! $detail instanceof DOMElement) {
                            return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_XML_DETAIL_NODE_INVALID'];
                        }
                        $row = [];
                        foreach (['codBCN', 'perOrigem', 'vOrigemIni', 'vSaldoAnt', 'vUtilPer', 'vSaldoFinal'] as $tag) {
                            $row[$tag] = $xpath->evaluate('string(d:'.$tag.')', $detail);
                        }
                        if ($row['perOrigem'] === $period && substr($row['codBCN'], -2) !== $sequence) {
                            return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_XML_CURRENT_ORIGIN_SEQUENCE_CONFLICT'];
                        }
                        $group['detBCN'][] = $row;
                    }
                }
                if ((string) count(array_unique(array_column($group['detBCN'], 'perOrigem'))) !== $declaredOrigins) {
                    return [...$identity, 'status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_XML_ORIGIN_COUNT_CONFLICT'];
                }
                $groups[] = $group;
            }
        }
        $normalized = ['supplier_id' => $supplier, 'period' => $period, 'groups' => $groups];
        $result = (new DereNegativeBaseReturnReconciler)->reconcile($normalized, $supplier, $period);

        return [...$identity, ...$result, 'normalized_return' => $normalized,
            'declared_receipt' => $value($event.'/d:infoRecEv/d:nrRecibo'), 'declared_sequence' => $sequence,
            'declared_registration_root' => $registrationRoot,
            'declared_processed_event_hash' => $scope['closing_event_hash']];
    }
}
