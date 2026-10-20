<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use DateTimeImmutable;
use DOMDocument;
use DOMXPath;

/** Synthetic successful event lifecycle only; final business payload needs native book reconciliation. */
final readonly class DerePublicOfferEventVersionReconciler
{
    /** @param array<string|int,mixed> $events
     * @param  array<string,mixed>  $scope
     * @return array<string,mixed>
     */
    public function select(array $events, string $supplier, string $period, DateTimeImmutable $knownAt, string $sources, array $scope): array
    {
        if (($scope['synthetic'] ?? null) !== true || ($scope['period_open'] ?? null) !== true
            || ! preg_match('/^[0-9]{14}$/', $supplier) || ! preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/', $period)
            || ! array_is_list($events) || count($events) < 1 || count($events) > 100) {
            return $this->stop('EXPLICIT_OPEN_SYNTHETIC_SCOPE_REQUIRED');
        }
        $active = null;
        $trace = [];
        $ids = $receipts = [];
        $previousTime = null;
        foreach ($events as $index => $event) {
            if (! is_array($event) || ! is_string($event['origin_xml'] ?? null) || ! is_string($event['return_xml'] ?? null)) {
                return $this->stop('RAW_EVENT_PAIR_REQUIRED');
            }
            foreach (['origin_xml' => 'evtDebOpOfPublic-v0_0_3.xsd', 'return_xml' => 'evtRetornoTitPub-v0_0_2.xsd'] as $key => $schema) {
                $validation = (new DereXmlSchemaValidator)->validate($event[$key],
                    $sources.'/dere-observed-20261006-cgibs-xsd-v1-3-0.zip',
                    '3d4de67215335e3b3186e92214ecbb8a55b501e846aba22eff59ca53e582dcdd', $schema,
                    $sources.'/w3c-xmldsig-core-schema-observed-20261002.xsd',
                    'd102ad3df7664c307e0c2c776ba4a90513b1969974d8a940bae1a77f9f21e15d');
                if ($validation['reason'] !== 'RTC_DERE_UNSIGNED_LOCAL_CANDIDATE') {
                    return $this->stop('SYNTHETIC_SCHEMA_UNPROVEN');
                }
            }
            $o = $this->path($event['origin_xml'], 'http://www.dere.gov.br/schemas/evtDebOpOfPublic/v0_0_3');
            $r = $this->path($event['return_xml'], 'http://www.dere.gov.br/schemas/evtRetornoTitPub/v0_0_2');
            $origin = '/d:DeRE/d:evtDebOpOfPublic';
            $returned = '/d:DeRE/d:evtRetornoTitPub';
            $id = $o->evaluate('string('.$origin.'/@id)');
            $receipt = $r->evaluate('string('.$returned.'/d:infoRecEv/d:nrRecibo)');
            $sequence = $r->evaluate('string('.$returned.'/d:infoRecEv/d:seqEvento)');
            $operation = $o->evaluate('string('.$origin.'/d:ideEvento/d:tpOper)');
            if (! is_string($id) || ! str_starts_with($id, 'DeRE21011'.$supplier) || in_array($id, $ids, true)
                || ! is_string($receipt) || $receipt === '' || in_array($receipt, $receipts, true)
                || $sequence !== sprintf('%02d', $index)
                || $r->evaluate('string('.$returned.'/@id)') !== $id
                || $o->evaluate('string('.$origin.'/d:ideContrib/d:nrInsc)') !== substr($supplier, 0, 8)
                || $r->evaluate('string('.$returned.'/d:ideContrib/d:nrInsc)') !== substr($supplier, 0, 8)
                || $o->evaluate('string('.$origin.'/d:idePeriodo/d:perApur)') !== $period
                || $r->evaluate('string('.$returned.'/d:infoEvento/d:idePeriodo/d:perApur)') !== $period
                || $o->evaluate('string('.$origin.'/d:ideEvento/d:tpAmb)') !== '2'
                || $r->evaluate('string('.$returned.'/d:ideStatus/d:cdRetorno)') !== '1'
                || $r->evaluate('string('.$returned.'/d:ideStatus/d:descRetorno)') !== 'Sucesso'
                || (int) $r->evaluate('count('.$returned.'/d:ideStatus/d:ocorrencias[d:tipo="1"])') !== 0
                || $r->evaluate('string('.$returned.'/d:infoRecEv/d:tpEv)') !== 'D-2101'
                || $r->evaluate('string('.$returned.'/d:infoRecEv/d:hash)') !== base64_encode(hash('sha256', $event['origin_xml'], true))) {
                return $this->stop('EVENT_PAIR_CONTEXT_SEQUENCE_OR_ID_CONFLICT');
            }
            $received = $this->time($r->evaluate('string('.$returned.'/d:infoRecEv/d:dhRecepcao)'));
            $processed = $this->time($r->evaluate('string('.$returned.'/d:infoRecEv/d:dhProcess)'));
            $known = [$knownAt->getTimestamp(), (int) ($knownAt->format('u').'0')];
            if ($received === null || $processed === null || $received > $processed || $processed > $known
                || ($previousTime !== null && $processed < $previousTime)) {
                return $this->stop('PROCESSING_CHRONOLOGY_OR_KNOWLEDGE_CONFLICT');
            }
            if (($active === null && $operation !== '1') || ($active !== null && ! in_array($operation, ['2', '3'], true))) {
                return $this->stop('ILLEGAL_VERSION_TRANSITION');
            }
            if ($operation === '1') {
                if ((int) $o->evaluate('count('.$origin.'/d:ideEvento/d:nrRecibo | '.$origin.'/d:ideEvento/d:motExcl | '.$origin.'/d:ideEvento/d:nrProc)') !== 0) {
                    return $this->stop('INCLUSION_REFERENCE_OR_EXCLUSION_FIELDS_CONFLICT');
                }
            } elseif ($active === null || $o->evaluate('string('.$origin.'/d:ideEvento/d:nrRecibo)') !== $active['receipt']) {
                return $this->stop('ACTIVE_RECEIPT_REFERENCE_CONFLICT');
            }
            if ($operation === '3') {
                $reason = $o->evaluate('string('.$origin.'/d:ideEvento/d:motExcl)');
                if (! in_array($reason, ['1', '2', '3', '9'], true)) {
                    return $this->stop('EXCLUSION_REASON_REQUIRED');
                }
                if ($reason === '1' || $o->evaluate('string('.$origin.'/d:ideEvento/d:nrProc)') !== '') {
                    return $this->stop('EXCLUSION_PROCEEDING_QUALIFICATION_REQUIRED');
                }
                if ((int) $r->evaluate('count('.$returned.'/d:infoEvento/d:infoAdic | '.$returned.'/d:infoEvento/d:infoTotTitOfPub)') !== 0
                    || (int) $o->evaluate('count('.$origin.'/d:infoDebOpOfPublic)') !== 0) {
                    return $this->stop('EXCLUSION_PAYLOAD_CONFLICT');
                }
                $active = null;
            } else {
                if ($operation === '2' && (int) $o->evaluate('count('.$origin.'/d:ideEvento/d:motExcl | '.$origin.'/d:ideEvento/d:nrProc)') !== 0) {
                    return $this->stop('ALTERATION_EXCLUSION_FIELDS_CONFLICT');
                }
                $pgcc = $r->evaluate('string('.$returned.'/d:infoEvento/d:infoAdic/d:nrReciboPGCC)');
                $reconciled = (new DerePublicOfferDebtReturnXmlReader)->read($event['return_xml'], $event['origin_xml'],
                    ['synthetic' => true, 'supplier_id' => $supplier, 'period' => $period,
                        'origin_xml_sha256' => hash('sha256', $event['origin_xml']), 'expected_sequence' => $sequence,
                        'pgcc_receipt' => $pgcc], $sources);
                if ($reconciled['status'] !== 'LOCAL_D9121_AGGREGATE_RECONCILED') {
                    return $this->stop('ACTIVE_RETURN_AGGREGATE_UNPROVEN');
                }
                $active = ['origin_xml' => $event['origin_xml'], 'return_xml' => $event['return_xml'],
                    'sequence' => $sequence, 'receipt' => $receipt, 'reconciliation' => $reconciled];
            }
            $ids[] = $id;
            $receipts[] = $receipt;
            $previousTime = $processed;
            $trace[] = ['sequence' => $sequence, 'operation' => $operation, 'receipt' => $receipt,
                'origin_sha256' => hash('sha256', $event['origin_xml']), 'return_sha256' => hash('sha256', $event['return_xml'])];
        }

        return ['status' => 'LOCAL_PUBLIC_EVENT_VERSION_CHAIN_RECONCILED', 'active_event' => $active,
            'trace' => $trace, 'chain_hash' => CanonicalJson::hash($events),
            'active_event_business_reconciled' => false, 'government_return_authenticated' => false,
            'eligible_for_composition' => false, 'eligible_for_posting' => false, 'production_ready' => false];
    }

    private function path(string $xml, string $namespace): DOMXPath
    {
        $document = new DOMDocument;
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $document->loadXML($xml, LIBXML_NONET);
        $path = new DOMXPath($document);
        $path->registerNamespace('d', $namespace);

        return $path;
    }

    /** @return array{int,int}|null */
    private function time(mixed $value): ?array
    {
        if (! is_string($value) || ! preg_match('/^(20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2})\.([0-9]{7})([+-][0-9]{2}:[0-9]{2})$/', $value, $matches)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $matches[1].$matches[3]);
        if ($date === false) {
            return null;
        }

        return [$date->getTimestamp(), (int) $matches[2]];
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DERE_PUBLIC_VERSION_'.$reason, 'production_ready' => false];
    }
}
