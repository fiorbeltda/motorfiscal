<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;

/** Local ordered close/reopen lifecycle; uses the successor snapshot, never adds version balances. */
final readonly class DereNegativeBaseClosingVersionReconciler
{
    /** @param list<array<string, mixed>> $events
     * @return array<string, mixed>
     */
    public function reconcile(array $events, string $supplier, string $period, string $sources, bool $syntheticUnsigned = false): array
    {
        if (! array_is_list($events) || $events === [] || count($events) > 199) {
            return $this->stop('EVENT_CHAIN_INVALID');
        }
        $state = 'INITIAL';
        $active = null;
        $sequence = -1;
        $receipts = $superseded = $trace = [];
        $openingOrigins = null;
        $lastProcessed = null;
        foreach ($events as $event) {
            if (! is_array($event) || ! is_string($event['expected_event_hash'] ?? null)
                || ! preg_match('/^[0-9A-Za-z+\/]{43}=$/', $event['expected_event_hash'])) {
                return $this->stop('EVENT_HASH_CONTEXT_INVALID');
            }
            if (($event['kind'] ?? null) === 'CLOSE') {
                if ($state === 'CLOSED' || ! is_string($event['xml'] ?? null)) {
                    return $this->stop('CLOSE_WITHOUT_REOPENING');
                }
                $result = (new DereNegativeBaseReturnXmlReader)->read($event['xml'],
                    ['supplier_id' => $supplier, 'period' => $period, 'closing_event_hash' => $event['expected_event_hash']],
                    $sources, $syntheticUnsigned);
                if ($result['status'] !== 'LOCAL_RETURN_RECONCILED') {
                    return $result;
                }
                if ((int) $result['declared_sequence'] !== $sequence + 1) {
                    return $this->stop('CLOSING_SEQUENCE_GAP_OR_REGRESSION');
                }
                $clock = $this->xpath($event['xml'], 'evtRetornoMensal/v0_0_2');
                $prefix = '/d:DeRE/d:evtRetornoMensal/d:infoRecEv';
                $received = $this->clock($clock->evaluate('string('.$prefix.'/d:dhRecepcao)'));
                $processed = $this->clock($clock->evaluate('string('.$prefix.'/d:dhProcess)'));
                if ($received === null || $processed === null || $processed < $received
                    || ($lastProcessed !== null && $received < $lastProcessed)) {
                    return $this->stop('EVENT_TIMESTAMP_ORDER_CONFLICT');
                }
                $lastProcessed = $processed;
                $receipt = $result['declared_receipt'];
                if (isset($receipts[$receipt])) {
                    return $this->stop('REUSED_RECEIPT');
                }
                $receipts[$receipt] = true;
                $origins = [];
                foreach ($result['normalized_return']['groups'] as $group) {
                    foreach ($group['detBCN'] as $row) {
                        if ($row['perOrigem'] < $period) {
                            $origins[$row['codBCN']] = ['codBCNRaiz' => $group['codBCNRaiz'],
                                'perOrigem' => $row['perOrigem'], 'vOrigemIni' => $row['vOrigemIni'], 'vSaldoAnt' => $row['vSaldoAnt']];
                        }
                    }
                }
                if ($openingOrigins !== null && CanonicalJson::hash($origins) !== CanonicalJson::hash($openingOrigins)) {
                    return $this->stop('PRIOR_ORIGIN_DEPENDENCY_REPROCESSING_REQUIRED');
                }
                $openingOrigins = $origins;
                if ($active !== null) {
                    $superseded[] = ['receipt' => $active['declared_receipt'], 'xml_sha256' => $active['xml_sha256']];
                }
                $active = $result;
                $sequence++;
                $state = 'CLOSED';
                $trace[] = ['kind' => 'CLOSE', 'receipt' => $receipt, 'sequence' => $result['declared_sequence'],
                    'xml_sha256' => $result['xml_sha256']];
            } elseif (($event['kind'] ?? null) === 'REOPEN') {
                if ($state !== 'CLOSED' || $active === null || ! is_string($event['request_xml'] ?? null)
                    || ! is_string($event['return_xml'] ?? null)) {
                    return $this->stop('REOPEN_WITHOUT_ACTIVE_CLOSING');
                }
                foreach (['request_xml' => 'evtReabertMensal-v0_0_1.xsd', 'return_xml' => 'evtRetornoReabert-v0_0_1.xsd'] as $field => $schemaName) {
                    $schema = (new DereXmlSchemaValidator)->validate($event[$field],
                        $sources.'/dere-observed-20261006-cgibs-xsd-v1-3-0.zip', DereNegativeBaseReturnXmlReader::SCHEMA_HASH,
                        $schemaName, $sources.'/w3c-xmldsig-core-schema-observed-20261002.xsd',
                        'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
                    if ($schema['status'] !== 'VALID' && ! ($syntheticUnsigned && $schema['reason'] === 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE')) {
                        return $this->stop('REOPENING_SCHEMA_UNPROVEN');
                    }
                }
                $request = $this->xpath($event['request_xml'], 'evtReabertMensal/v0_0_1');
                $response = $this->xpath($event['return_xml'], 'evtRetornoReabert/v0_0_1');
                $rq = '/d:DeRE/d:evtReabertMensal';
                $rp = '/d:DeRE/d:evtRetornoReabert';
                if ($request->evaluate('string('.$rq.'/d:ideContrib/d:nrInsc)') !== substr($supplier, 0, 8)
                    || $request->evaluate('string('.$rq.'/d:idePeriodo/d:perApur)') !== $period
                    || $request->evaluate('string('.$rq.'/d:infoReabertura/d:nrReciboReab)') !== $active['declared_receipt']
                    || $request->evaluate('string('.$rq.'/d:ideEvento/d:tpAmb)') !== ($syntheticUnsigned ? '2' : '1')
                    || $response->evaluate('string('.$rp.'/d:ideContrib/d:nrInsc)') !== substr($supplier, 0, 8)
                    || $response->evaluate('string('.$rp.'/d:infoEvento/d:idePeriodo/d:perApur)') !== $period
                    || $response->evaluate('string('.$rp.'/d:ideStatus/d:cdRetorno)') !== '1'
                    || $response->evaluate('string('.$rp.'/d:infoRecEv/d:tpEv)') !== 'D-1198'
                    || $response->evaluate('string('.$rp.'/d:infoRecEv/d:hash)') !== $event['expected_event_hash']) {
                    return $this->stop('REOPENING_SCOPE_OR_ACTIVE_RECEIPT_CONFLICT');
                }
                $receipt = $response->evaluate('string('.$rp.'/d:infoRecEv/d:nrRecibo)');
                if (! is_string($receipt) || $receipt === '' || isset($receipts[$receipt])) {
                    return $this->stop('REOPENING_RECEIPT_MISSING_OR_REUSED');
                }
                $received = $this->clock($response->evaluate('string('.$rp.'/d:infoRecEv/d:dhRecepcao)'));
                $processed = $this->clock($response->evaluate('string('.$rp.'/d:infoRecEv/d:dhProcess)'));
                if ($received === null || $processed === null || $processed < $received
                    || $received < $lastProcessed) {
                    return $this->stop('EVENT_TIMESTAMP_ORDER_CONFLICT');
                }
                $lastProcessed = $processed;
                $receipts[$receipt] = true;
                $state = 'OPEN';
                $trace[] = ['kind' => 'REOPEN', 'receipt' => $receipt, 'reopened_receipt' => $active['declared_receipt'],
                    'request_xml_sha256' => hash('sha256', $event['request_xml']), 'return_xml_sha256' => hash('sha256', $event['return_xml'])];
            } else {
                return $this->stop('EVENT_KIND_INVALID');
            }
        }
        if ($state !== 'CLOSED') {
            return $this->stop('PERIOD_OPEN_WITHOUT_SUCCESSOR_CLOSING');
        }

        return ['status' => 'LOCAL_CLOSING_VERSION_CHAIN_RECONCILED', 'supplier_id' => $supplier, 'period' => $period,
            'chain_hash' => CanonicalJson::hash($events), 'active_return' => $active, 'superseded' => $superseded, 'trace' => $trace,
            'received_return_verified' => false, 'declared_timestamp_order_verified' => true,
            'dependent_periods_reprocessed' => false, 'eligible_for_posting' => false, 'production_ready' => false];
    }

    /** @return array{int, int}|null */
    private function clock(mixed $value): ?array
    {
        if (! is_string($value) || ! preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})\.(\d{7})([+-]\d{2}:\d{2})$/', $value, $parts)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $parts[1].$parts[3]);
        if ($date === false || $date->format('Y-m-d\TH:i:sP') !== $parts[1].$parts[3]) {
            return null;
        }

        return [(int) $date->format('U'), (int) $parts[2]];
    }

    private function xpath(string $xml, string $namespace): DOMXPath
    {
        $document = new DOMDocument;
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $document->loadXML($xml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('d', 'http://www.dere.gov.br/schemas/'.$namespace);

        return $xpath;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_BCN_VERSION_'.$reason,
            'eligible_for_posting' => false, 'production_ready' => false];
    }
}
